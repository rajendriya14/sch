<?php

namespace Services;

use Config\Database;
use Models\Employee;
use Models\Shift;
use Models\ForecastRequirement;
use Models\LeaveRequest;
use Models\TlSchedule;
use PDO;

/**
 * Core Scheduling Engine
 *
 * Generates weekly schedules using a greedy heuristic algorithm.
 * Business rules are implemented as modular methods that can be
 * enabled/disabled and extended.
 */
class SchedulingEngine
{
    /** @var array All active employees */
    private array $employees = [];

    /** @var array All shifts indexed by code */
    private array $shifts = [];

    /** @var array Hours covered by each shift [shift_code => [hours...]] */
    private array $shiftHourMap = [];

    /** @var array Female-restricted shift codes */
    private array $femaleRestricted = [];

    /** @var array Rule instances */
    private array $rules = [];

    /**
     * Generate a schedule for a given week.
     *
     * @param string $weekStartDate Monday Y-m-d
     * @return array ['schedule' => [...], 'violations' => [...]]
     */
    public function generate(string $weekStartDate, ?string $domain = null): array
    {
        // Load master data (only active shifts)
        if ($domain) {
            $this->employees = Employee::getByDomain($domain);
        } else {
            $this->employees = Employee::allActive();
        }
        $this->shifts = $this->indexShifts(Shift::getActive());
        $this->shiftHourMap = $this->buildShiftHourMap($this->shifts);
        $this->femaleRestricted = array_unique(array_merge(
            Shift::getFemaleRestrictedCodes(),
            ['S5', 'S6', 'S7', 'M1', 'M2', 'M3']
        ));

        // Build context
        $weekEndDate = date('Y-m-d', strtotime($weekStartDate . ' +6 days'));
        $forecast = ForecastRequirement::allGrouped();
        $leaves = LeaveRequest::getApprovedForWeek($weekStartDate, $weekEndDate);
        $tlSchedules = TlSchedule::allGrouped();
        $weekDates = $this->getWeekDates($weekStartDate);

        // Load backdate reference data (staff_email => [Mon..Sun shift codes])
        $backdateReference = $this->loadBackdateReference($weekStartDate);

        // Initialize empty schedule grid: [employee_id][day_of_week] = shift_code
        $schedule = [];
        foreach ($this->employees as $emp) {
            for ($d = 0; $d < 7; $d++) {
                $schedule[$emp['id']][$d] = null;
            }
        }

        $context = [
            'forecast'    => $forecast,
            'leaves'      => $leaves,
            'tlSchedules' => $tlSchedules,
            'weekDates'   => $weekDates,
            'weekStart'   => $weekStartDate,
        ];

        // === STEP 1: Apply leave OFF ===
        $this->applyLeaveOff($schedule, $leaves);

        // === STEP 2: Weekly OFF — exactly 2 days, spread out (not consecutive) ===
        $this->applyWeeklyOff($schedule);

        // === STEP 3: Assign shifts to cover forecast headcount ===
        $this->assignShiftsByForecast($schedule, $forecast);

        // === STEP 4: Female restriction — no female on restricted shifts ===
        $this->applyFemaleRestriction($schedule);

        // === STEP 5: Max 6 consecutive working days ===
        $this->applyConsecutiveDays($schedule);

        // === STEP 6: M3 rule — after M3 only M3 or OFF ===
        $this->applyM3Rule($schedule);

        // === STEP 7: Optimize TL meeting (ensure at least 1 shared shift) ===
        $this->optimizeTLMeeting($schedule, $tlSchedules);

        // === STEP 8: Fair shift distribution ===
        $this->balanceShiftDistribution($schedule);

        // === STEP 9: Run registered rules ===
        $allViolations = [];
        foreach ($this->rules as $rule) {
            $violations = $rule->apply($schedule, $this->employees, $this->shifts, $context);
            if (!empty($violations)) {
                $allViolations = array_merge($allViolations, $violations);
            }
        }

        return [
            'schedule'   => $schedule,
            'violations' => $allViolations,
            'weekDates'  => $weekDates,
        ];
    }

    // ============================================================
    // RULE: Backdate Reference
    // ============================================================
    /**
     * Load backdate schedule reference for the same week.
     * Maps staff_email => [tl_email, gender, shifts[7]]
     */
    private function loadBackdateReference(string $weekStart): array
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM backdate_data WHERE week_start_date = ?");
            $stmt->execute([$weekStart]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $reference = [];
            foreach ($rows as $row) {
                $lines = explode("\n", trim($row['import_data'] ?? ''));
                foreach ($lines as $line) {
                    $cells = explode("\t", $line);
                    if (count($cells) < 3) continue;
                    $email = strtolower(trim($cells[0]));
                    $reference[$email] = [
                        'tl_email' => trim($cells[1] ?? ''),
                        'gender'   => trim($cells[2] ?? ''),
                        'shifts'   => array_map('trim', array_slice($cells, 3, 7)),
                    ];
                }
            }
            return $reference;
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Seed the schedule grid with backdate reference where available.
     * Only applies shifts that are still valid (female restriction, M3, active shifts).
     */
    private function applyBackdateReference(array &$schedule, array $backdateReference): void
    {
        if (empty($backdateReference)) {
            return;
        }

        // Build employee email => id + gender map
        $emailToEmp = [];
        foreach ($this->employees as $emp) {
            $emailToEmp[strtolower(trim($emp['email']))] = $emp;
        }

        foreach ($backdateReference as $email => $ref) {
            if (!isset($emailToEmp[$email])) {
                continue;
            }
            $emp = $emailToEmp[$email];
            $empId = (int)$emp['id'];
            $isFemale = ($emp['gender'] === 'Female');

            foreach ($ref['shifts'] as $d => $shiftCode) {
                $d = (int)$d;
                if ($d > 6) continue;
                if ($shiftCode === '' || $shiftCode === 'OFF') {
                    $schedule[$empId][$d] = 'OFF';
                    continue;
                }
                // Skip female-restricted shifts
                if ($isFemale && in_array($shiftCode, $this->femaleRestricted)) {
                    continue;
                }
                // Skip leave-type / inactive shifts
                if (!isset($this->shifts[$shiftCode])) {
                    continue;
                }
                if (isset($this->shifts[$shiftCode]['category']) && $this->shifts[$shiftCode]['category'] === 'Leave') {
                    continue;
                }
                $schedule[$empId][$d] = $shiftCode;
            }
        }
    }


    // ============================================================
    // RULE: Leave OFF
    // ============================================================
    private function applyLeaveOff(array &$schedule, array $leaves): void
    {
        $leaveByEmpDay = [];
        foreach ($leaves as $leave) {
            $empId = (int)$leave['employee_id'];
            $date = $leave['request_date'];
            $dayOfWeek = (int)date('N', strtotime($date)) - 1; // 0=Monday
            $leaveByEmpDay[$empId][$dayOfWeek] = true;
        }

        foreach ($schedule as $empId => &$days) {
            foreach ($days as $d => &$shift) {
                if (isset($leaveByEmpDay[$empId][$d])) {
                    $shift = 'OFF';
                }
            }
        }
    }

    // ============================================================
    // RULE: Weekly OFF — exactly 2 OFF days, spread across the week
    // ============================================================
    private function applyWeeklyOff(array &$schedule): void
    {
        foreach ($schedule as $empId => &$days) {
            // Count existing OFF days
            $offDays = [];
            foreach ($days as $d => $shift) {
                if ($shift === 'OFF') {
                    $offDays[] = $d;
                }
            }

            // Keep exactly 2 OFF days
            $needed = 2 - count($offDays);
            if ($needed <= 0) continue;

            // Candidates: days not yet OFF
            $candidates = [];
            for ($d = 0; $d < 7; $d++) {
                if (!in_array($d, $offDays)) {
                    $candidates[] = $d;
                }
            }
            // Prefer unassigned (null) days first, then spread them out
            usort($candidates, function ($a, $b) use ($days) {
                $aNull = $days[$a] === null ? 0 : 1;
                $bNull = $days[$b] === null ? 0 : 1;
                if ($aNull !== $bNull) return $aNull - $bNull;
                // Prefer spread: distance from existing OFF days
                $aDist = 9; $bDist = 9;
                foreach (array_keys($days) as $d) {
                    if ($days[$d] === 'OFF') {
                        $aDist = min($aDist, abs($a - $d));
                        $bDist = min($bDist, abs($b - $d));
                    }
                }
                return $bDist - $aDist; // prefer larger distance
            });

            for ($i = 0; $i < $needed && $i < count($candidates); $i++) {
                $days[$candidates[$i]] = 'OFF';
            }

            // Re-verify the 2 OFF days are not adjacent; if so, spread them
            $offDays = [];
            foreach ($days as $d => $shift) {
                if ($shift === 'OFF') $offDays[] = $d;
            }
            if (count($offDays) === 2) {
                $diff = abs($offDays[0] - $offDays[1]);
                if ($diff <= 1) {
                    // Move the second OFF to a day far from the first
                    for ($d = 0; $d < 7; $d++) {
                        if (!in_array($d, $offDays) && abs($d - $offDays[0]) >= 2) {
                            $days[$offDays[1]] = null;
                            $days[$d] = 'OFF';
                            break;
                        }
                    }
                }
            }
        }
    }

    // ============================================================
    // RULE: Consecutive Working Days (max 6)
    // ============================================================
    private function applyConsecutiveDays(array &$schedule): void
    {
        foreach ($schedule as $empId => &$days) {
            $consecutive = 0;
            for ($d = 0; $d < 7; $d++) {
                $isOff = ($days[$d] === 'OFF');
                if ($isOff) {
                    $consecutive = 0;
                } else {
                    $consecutive++;
                    if ($consecutive > 6) {
                        $days[$d] = 'OFF';
                        $consecutive = 0;
                    }
                }
            }
        }
    }

    // ============================================================
    // CORE: Assign shifts to cover forecast
    // ============================================================
    private function assignShiftsByForecast(array &$schedule, array $forecast): void
    {
        $allShiftCodes = array_keys($this->shifts);

        // Process each day independently
        for ($d = 0; $d < 7; $d++) {
            // Determine which employees are available this day (not OFF)
            $availableEmployees = [];
            foreach ($this->employees as $emp) {
                if ($schedule[$emp['id']][$d] === null) {
                    $availableEmployees[] = $emp;
                }
            }

            if (empty($availableEmployees)) {
                continue;
            }

            // Calculate deficit per hour
            $deficitByHour = [];
            for ($h = 0; $h < 24; $h++) {
                $required = $forecast[$d][$h] ?? 0;
                // We'll calculate assigned count after we assign shifts
                $deficitByHour[$h] = $required;
            }

            // Assign shifts to employees using greedy approach
            // Sort employees - we'll rotate to ensure fairness
            $empPool = $availableEmployees;
            shuffle($empPool); // Randomize initial order for fairness

            foreach ($empPool as $emp) {
                $empId = $emp['id'];
                if ($schedule[$empId][$d] !== null) {
                    continue; // Already assigned
                }

                $bestShift = $this->findBestShiftForEmployee($emp, $deficitByHour, $d, $schedule);
                if ($bestShift) {
                    $schedule[$empId][$d] = $bestShift;
                    // Update deficit by subtracting this employee's coverage
                    $this->updateDeficit($deficitByHour, $bestShift);
                }
            }

            // Assign remaining unassigned employees any valid shift
            foreach ($empPool as $emp) {
                $empId = $emp['id'];
                if ($schedule[$empId][$d] !== null) {
                    continue;
                }

                $shiftCode = $this->findAnyValidShift($emp);
                if ($shiftCode) {
                    $schedule[$empId][$d] = $shiftCode;
                }
            }
        }
    }

    /**
     * Find the best shift for an employee given current deficits
     * Skips LEAVE type shifts
     */
    private function findBestShiftForEmployee(array $emp, array &$deficitByHour, int $day, array &$schedule): ?string
    {
        $isFemale = ($emp['gender'] === 'Female');
        $prevDayShift = ($day > 0) ? ($schedule[$emp['id']][$day - 1] ?? null) : null;

        $bestShift = null;
        $bestScore = -999;

        foreach ($this->shifts as $code => $shift) {
            // Skip female-restricted shifts
            if ($isFemale && in_array($code, $this->femaleRestricted)) {
                continue;
            }

            // Skip leave type shifts
            if (isset($shift['category']) && $shift['category'] === 'Leave') {
                continue;
            }

            // M3 rule: if previous day was M3, next day must be OFF or M3
            if ($prevDayShift === 'M3' && $code !== 'M3') {
                continue;
            }

            // Calculate how much this shift covers high-deficit hours
            $hours = $this->shiftHourMap[$code] ?? [];
            $score = 0;
            foreach ($hours as $h) {
                $deficit = $deficitByHour[$h] ?? 0;
                if ($deficit > 0) {
                    $score += $deficit * 2; // Prioritize high deficit
                } else {
                    $score += max($deficit, -1); // Slight penalty for over-coverage
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestShift = $code;
            }
        }

        return $bestShift;
    }

    /**
     * Find any valid shift for an employee (fallback)
     * Skips LEAVE type shifts — they are not working shifts
     */
    private function findAnyValidShift(array $emp): ?string
    {
        $isFemale = ($emp['gender'] === 'Female');

        foreach ($this->shifts as $code => $shift) {
            if ($isFemale && in_array($code, $this->femaleRestricted)) {
                continue;
            }
            // Skip leave type shifts
            if (isset($shift['category']) && $shift['category'] === 'Leave') {
                continue;
            }
            return $code;
        }

        return null;
    }

    /**
     * Update deficit after assigning an employee a shift
     */
    private function updateDeficit(array &$deficitByHour, string $shiftCode): void
    {
        $hours = $this->shiftHourMap[$shiftCode] ?? [];
        foreach ($hours as $h) {
            if (isset($deficitByHour[$h]) && $deficitByHour[$h] > 0) {
                $deficitByHour[$h]--;
            }
        }
    }

    // ============================================================
    // RULE: Female Restriction
    // ============================================================
    private function applyFemaleRestriction(array &$schedule): void
    {
        foreach ($this->employees as $emp) {
            if ($emp['gender'] !== 'Female') {
                continue;
            }
            $empId = (int)$emp['id'];
            for ($d = 0; $d < 7; $d++) {
                $shift = $schedule[$empId][$d] ?? null;
                if ($shift && in_array($shift, $this->femaleRestricted)) {
                    // Find a replacement shift
                    $replacement = $this->findAlternativeShift($emp, $d, $schedule);
                    $schedule[$empId][$d] = $replacement ?: 'OFF';
                }
            }
        }
    }

    /**
     * Find an alternative non-restricted shift
     */
    private function findAlternativeShift(array $emp, int $day, array &$schedule): ?string
    {
        $prevDayShift = ($day > 0) ? ($schedule[$emp['id']][$day - 1] ?? null) : null;

        foreach ($this->shifts as $code => $shift) {
            if (in_array($code, $this->femaleRestricted)) {
                continue;
            }
            // M3 rule
            if ($prevDayShift === 'M3' && $code !== 'M3') {
                continue;
            }
            return $code;
        }
        return null;
    }

    // ============================================================
    // RULE: M3 Constraint
    // ============================================================
    private function applyM3Rule(array &$schedule): void
    {
        foreach ($schedule as $empId => &$days) {
            for ($d = 1; $d < 7; $d++) {
                $prevShift = $days[$d - 1] ?? null;
                $currShift = $days[$d] ?? null;

                if ($prevShift === 'M3' && $currShift !== null && $currShift !== 'OFF' && $currShift !== 'M3') {
                    // Violation found - fix by changing current day
                    $days[$d] = 'OFF';
                }
            }
        }
    }

    // ============================================================
    // RULE: TL Meeting Optimization
    // ============================================================
    private function optimizeTLMeeting(array &$schedule, array $tlSchedules): void
    {
        if (empty($tlSchedules)) {
            return;
        }

        foreach ($this->employees as $emp) {
            $empId = (int)$emp['id'];
            $teamLeader = $emp['team_leader'];

            if (empty($teamLeader) || !isset($tlSchedules[$teamLeader])) {
                continue;
            }

            $tlWeek = $tlSchedules[$teamLeader];

            // Check if employee already shares at least one shift with TL
            $hasMeeting = false;
            for ($d = 0; $d < 7; $d++) {
                $empShift = $schedule[$empId][$d] ?? null;
                $tlShift = $tlWeek[$d] ?? null;

                if ($empShift && $tlShift && $empShift === $tlShift && $empShift !== 'OFF') {
                    $hasMeeting = true;
                    break;
                }
            }

            if ($hasMeeting) {
                continue;
            }

            // Try to align employee shift with TL on at least one day
            for ($d = 0; $d < 7; $d++) {
                $tlShift = $tlWeek[$d] ?? null;
                if (!$tlShift || $tlShift === 'OFF') {
                    continue;
                }

                if (($schedule[$empId][$d] ?? null) !== null && $schedule[$empId][$d] !== 'OFF') {
                    // Check if we can swap to TL's shift
                    $isFemale = ($emp['gender'] === 'Female');
                    if ($isFemale && in_array($tlShift, $this->femaleRestricted)) {
                        continue;
                    }

                    // Check M3 constraint
                    $prevDayShift = ($d > 0) ? ($schedule[$empId][$d - 1] ?? null) : null;
                    if ($prevDayShift === 'M3' && $tlShift !== 'M3') {
                        continue;
                    }

                    // Assign the TL's shift to this employee
                    $schedule[$empId][$d] = $tlShift;
                    break;
                }
            }
        }
    }

    // ============================================================
    // RULE: Fair Shift Distribution
    // ============================================================
    private function balanceShiftDistribution(array &$schedule): void
    {
        $categories = ['Morning', 'Swing', 'Night'];

        foreach ($this->employees as $emp) {
            $empId = (int)$emp['id'];
            $isFemale = ($emp['gender'] === 'Female');

            // Count assigned shifts per category
            $categoryCount = ['Morning' => 0, 'Swing' => 0, 'Night' => 0];

            for ($d = 0; $d < 7; $d++) {
                $shiftCode = $schedule[$empId][$d] ?? null;
                if ($shiftCode && $shiftCode !== 'OFF' && isset($this->shifts[$shiftCode])) {
                    $cat = $this->shifts[$shiftCode]['category'];
                    $categoryCount[$cat]++;
                }
            }

            // Find the least assigned category and try to swap
            $totalAssigned = array_sum($categoryCount);
            if ($totalAssigned <= 1) {
                continue;
            }

            // Try to balance by adjusting unassigned/unimportant days
            // This is a light optimization — full balancing would require
            // re-running the forecast assignment with category constraints
        }
    }

    // ============================================================
    // Rule Management
    // ============================================================

    /**
     * Register a custom rule
     */
    public function addRule(RuleInterface $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * Get all registered rules
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Index shifts by code for quick lookup
     */
    private function indexShifts(array $shifts): array
    {
        $indexed = [];
        foreach ($shifts as $s) {
            $indexed[$s['shift_code']] = $s;
        }
        return $indexed;
    }

    /**
     * Build a map of which hours each shift covers.
     * For simplicity, assumes coverage during working hours.
     */
    private function buildShiftHourMap(array $shifts): array
    {
        $map = [];
        foreach ($shifts as $code => $shift) {
            $start = (int)date('G', strtotime($shift['start_time']));
            $end = (int)date('G', strtotime($shift['end_time']));
            $hours = [];

            if ($end > $start) {
                for ($h = $start; $h < $end; $h++) {
                    $hours[] = $h;
                }
            } else {
                // Overnight shift (e.g., 22:00 - 07:00)
                for ($h = $start; $h < 24; $h++) {
                    $hours[] = $h;
                }
                for ($h = 0; $h < $end; $h++) {
                    $hours[] = $h;
                }
            }
            $map[$code] = $hours;
        }
        return $map;
    }

    /**
     * Get dates for a week
     */
    private function getWeekDates(string $monday): array
    {
        $dates = [];
        $ts = strtotime($monday);
        for ($i = 0; $i < 7; $i++) {
            $dates[$i] = date('Y-m-d', strtotime("+{$i} days", $ts));
        }
        return $dates;
    }
}

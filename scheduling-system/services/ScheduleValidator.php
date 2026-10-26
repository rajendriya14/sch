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
 * Schedule Validator
 *
 * Evaluates a generated schedule against all business rules
 * and produces a structured validation summary.
 */
class ScheduleValidator
{
    private array $employees = [];
    private array $shifts = [];
    private array $femaleRestricted = [];

    public function __construct()
    {
        $this->employees = Employee::allActive();
        $this->shifts = $this->indexShifts(Shift::getAll());
        $this->femaleRestricted = Shift::getFemaleRestrictedCodes();
    }

    /**
     * Run all validations against a schedule.
     *
     * @param array $schedule [employee_id][day_of_week] = shift_code
     * @return array ['rules' => [...], 'overall_passed' => bool]
     */
    public function validate(array $schedule): array
    {
        $forecast = ForecastRequirement::allGrouped();
        $tlSchedules = TlSchedule::allGrouped();

        $coverageResult = $this->validateForecastCoverage($schedule, $forecast);
        $leaveOffResult = $this->validateRequestOff($schedule);
        $femaleResult = $this->validateFemaleRestriction($schedule);
        $m3Result = $this->validateM3Rule($schedule);
        $consecutiveResult = $this->validateConsecutiveDays($schedule);
        $weeklyOffResult = $this->validateWeeklyOff($schedule);
        $tlResult = $this->validateTLMeeting($schedule, $tlSchedules);

        $rules = [
            $coverageResult,
            $leaveOffResult,
            $femaleResult,
            $m3Result,
            $consecutiveResult,
            $weeklyOffResult,
            $tlResult,
        ];

        $allPassed = true;
        foreach ($rules as $rule) {
            if (!$rule['passed']) {
                $allPassed = false;
            }
        }

        return [
            'rules'          => $rules,
            'overall_passed' => $allPassed,
        ];
    }

    // ============================================================
    // 1. Forecast Coverage
    // ============================================================
    public function validateForecastCoverage(array $schedule, array $forecast): array
    {
        $totalRequired = 0;
        $totalCovered = 0;
        $violations = [];

        // Build assigned count per day/hour
        $assigned = [];
        foreach ($schedule as $empId => $days) {
            foreach ($days as $d => $shiftCode) {
                if ($shiftCode === 'OFF' || $shiftCode === null) {
                    continue;
                }
                $hours = $this->getShiftHours($shiftCode);
                foreach ($hours as $h) {
                    $assigned[$d][$h] = ($assigned[$d][$h] ?? 0) + 1;
                }
            }
        }

        foreach ($forecast as $d => $hours) {
            foreach ($hours as $h => $required) {
                $totalRequired += $required;
                $covered = min($assigned[$d][$h] ?? 0, $required);
                $totalCovered += $covered;

                if (($assigned[$d][$h] ?? 0) < $required) {
                    $dayName = date('l', strtotime("Sunday +{$d} days"));
                    $violations[] = sprintf(
                        '%s %02d:00 — required %d, assigned %d',
                        $dayName,
                        $h,
                        $required,
                        $assigned[$d][$h] ?? 0
                    );
                }
            }
        }

        $percentage = $totalRequired > 0 ? round(($totalCovered / $totalRequired) * 100, 1) : 100;

        return [
            'name'       => 'Forecast Coverage',
            'passed'     => $percentage >= 90,
            'percentage' => $percentage,
            'details'    => "{$totalCovered} of {$totalRequired} required slots covered (gaps at " . count($violations) . " hour-slots)",
            'violations' => $violations,
        ];
    }

    // ============================================================
    // 2. Request OFF Compliance
    // ============================================================
    public function validateRequestOff(array $schedule): array
    {
        $violations = [];

        // Get today's week or latest leaves
        $leaves = LeaveRequest::all();
        $leaveByEmp = [];

        foreach ($leaves as $leave) {
            if ($leave['status'] !== 'Approved') {
                continue;
            }
            // Match employee_id to schedule entries
            $empId = (int)$leave['employee_id'];
            $date = $leave['request_date'];
            $dayOfWeek = (int)date('N', strtotime($date)) - 1;

            if (!isset($leaveByEmp[$empId])) {
                $leaveByEmp[$empId] = [];
            }
            $leaveByEmp[$empId][$dayOfWeek] = $date;
        }

        foreach ($leaveByEmp as $empId => $leaveDays) {
            $emp = Employee::find($empId);
            $empName = $emp ? $emp['name'] : "Employee #{$empId}";

            foreach ($leaveDays as $dayOfWeek => $date) {
                if (!isset($schedule[$empId][$dayOfWeek]) || $schedule[$empId][$dayOfWeek] !== 'OFF') {
                    $dayName = date('l', strtotime($date));
                    $violations[] = "{$empName} has approved leave on {$dayName} ({$date}) but is not marked OFF.";
                }
            }
        }

        return [
            'name'       => 'Request OFF Compliance',
            'passed'     => empty($violations),
            'percentage' => empty($violations) ? 100 : 0,
            'details'    => empty($violations)
                ? 'All approved leave requests are respected.'
                : count($violations) . ' violation(s) found.',
            'violations' => $violations,
        ];
    }

    // ============================================================
    // 3. Female Restriction
    // ============================================================
    public function validateFemaleRestriction(array $schedule): array
    {
        $violations = [];

        foreach ($this->employees as $emp) {
            if ($emp['gender'] !== 'Female') {
                continue;
            }
            $empId = (int)$emp['id'];
            for ($d = 0; $d < 7; $d++) {
                $shiftCode = $schedule[$empId][$d] ?? null;
                if ($shiftCode && in_array($shiftCode, $this->femaleRestricted)) {
                    $dayName = date('l', strtotime("Sunday +{$d} days"));
                    $violations[] = "{$emp['name']} assigned restricted shift {$shiftCode} on {$dayName}.";
                }
            }
        }

        return [
            'name'       => 'Female Shift Restrictions',
            'passed'     => empty($violations),
            'percentage' => empty($violations) ? 100 : 0,
            'details'    => empty($violations)
                ? 'No female employees assigned to restricted shifts.'
                : count($violations) . ' violation(s) found.',
            'violations' => $violations,
        ];
    }

    // ============================================================
    // 4. M3 Rule
    // ============================================================
    public function validateM3Rule(array $schedule): array
    {
        $violations = [];

        foreach ($schedule as $empId => $days) {
            $emp = Employee::find($empId);
            $empName = $emp ? $emp['name'] : "Employee #{$empId}";

            for ($d = 1; $d < 7; $d++) {
                $prev = $days[$d - 1] ?? null;
                $curr = $days[$d] ?? null;

                if ($prev === 'M3' && $curr !== null && $curr !== 'OFF' && $curr !== 'M3') {
                    $dayName = date('l', strtotime("Sunday +{$d} days"));
                    $violations[] = "{$empName} worked M3 on day before {$dayName} but is assigned {$curr}.";
                }
            }
        }

        return [
            'name'       => 'M3 Rule Compliance',
            'passed'     => empty($violations),
            'percentage' => empty($violations) ? 100 : 0,
            'details'    => empty($violations)
                ? 'No M3 rule violations.'
                : count($violations) . ' violation(s) found.',
            'violations' => $violations,
        ];
    }

    // ============================================================
    // 5. Consecutive Working Days
    // ============================================================
    public function validateConsecutiveDays(array $schedule): array
    {
        $violations = [];

        foreach ($schedule as $empId => $days) {
            $emp = Employee::find($empId);
            $empName = $emp ? $emp['name'] : "Employee #{$empId}";

            $consecutive = 0;
            for ($d = 0; $d < 7; $d++) {
                $isOff = ($days[$d] === 'OFF' || $days[$d] === null);
                if ($isOff) {
                    $consecutive = 0;
                } else {
                    $consecutive++;
                    if ($consecutive > 6) {
                        $violations[] = "{$empName} works {$consecutive} consecutive days (max 6).";
                    }
                }
            }
        }

        return [
            'name'       => 'Consecutive Working Days',
            'passed'     => empty($violations),
            'percentage' => empty($violations) ? 100 : 0,
            'details'    => empty($violations)
                ? 'No employee exceeds 6 consecutive working days.'
                : count($violations) . ' violation(s) found.',
            'violations' => $violations,
        ];
    }

    // ============================================================
    // 6. Weekly OFF
    // ============================================================
    public function validateWeeklyOff(array $schedule): array
    {
        $violations = [];

        foreach ($schedule as $empId => $days) {
            $emp = Employee::find($empId);
            $empName = $emp ? $emp['name'] : "Employee #{$empId}";

            $offCount = 0;
            foreach ($days as $d => $shift) {
                if ($shift === 'OFF') {
                    $offCount++;
                }
            }

            if ($offCount < 2) {
                $violations[] = "{$empName} has only {$offCount} OFF day(s) (minimum 2 required).";
            }
        }

        $totalEmployees = count($schedule);
        $compliant = $totalEmployees - count($violations);
        $percentage = $totalEmployees > 0 ? round(($compliant / $totalEmployees) * 100, 1) : 100;

        return [
            'name'       => 'Weekly OFF Compliance',
            'passed'     => empty($violations),
            'percentage' => $percentage,
            'details'    => "{$compliant} of {$totalEmployees} employees have at least 2 OFF days.",
            'violations' => $violations,
        ];
    }

    // ============================================================
    // 7. Team Leader Meeting
    // ============================================================
    public function validateTLMeeting(array $schedule, array $tlSchedules): array
    {
        $violations = [];

        foreach ($this->employees as $emp) {
            $empId = (int)$emp['id'];
            $tl = $emp['team_leader'];

            if (empty($tl) || !isset($tlSchedules[$tl])) {
                continue;
            }

            $tlWeek = $tlSchedules[$tl];
            $hasMeeting = false;

            for ($d = 0; $d < 7; $d++) {
                $empShift = $schedule[$empId][$d] ?? null;
                $tlShift = $tlWeek[$d] ?? null;

                if ($empShift && $tlShift && $empShift === $tlShift && $empShift !== 'OFF') {
                    $hasMeeting = true;
                    break;
                }
            }

            if (!$hasMeeting) {
                $violations[] = "{$emp['name']} (TL: {$tl}) does not share any shift with Team Leader.";
            }
        }

        $totalEmployees = count($this->employees);
        $withTL = 0;
        foreach ($this->employees as $emp) {
            if (!empty($emp['team_leader'])) {
                $withTL++;
            }
        }
        $compliant = $withTL - count($violations);
        $percentage = $withTL > 0 ? round(($compliant / $withTL) * 100, 1) : 100;

        return [
            'name'       => 'Team Leader Meeting',
            'passed'     => empty($violations),
            'percentage' => $percentage,
            'details'    => "{$compliant} of {$withTL} employees with a TL share a shift with them.",
            'violations' => $violations,
        ];
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function indexShifts(array $shifts): array
    {
        $indexed = [];
        foreach ($shifts as $s) {
            $indexed[$s['shift_code']] = $s;
        }
        return $indexed;
    }

    private function getShiftHours(string $shiftCode): array
    {
        if (!isset($this->shifts[$shiftCode])) {
            return [];
        }

        $shift = $this->shifts[$shiftCode];
        $start = (int)date('G', strtotime($shift['start_time']));
        $end = (int)date('G', strtotime($shift['end_time']));
        $hours = [];

        if ($end > $start) {
            for ($h = $start; $h < $end; $h++) {
                $hours[] = $h;
            }
        } else {
            for ($h = $start; $h < 24; $h++) {
                $hours[] = $h;
            }
            for ($h = 0; $h < $end; $h++) {
                $hours[] = $h;
            }
        }
        return $hours;
    }
}

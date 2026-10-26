<?php

namespace Services;

use Models\TlSchedule;
use Models\Employee;
use Models\Shift;

class TlScheduleService
{
    /**
     * Get all TL schedules
     */
    public function getAll(): array
    {
        return TlSchedule::all();
    }

    /**
     * Get schedule for a specific TL
     */
    public function getByTeamLeader(string $teamLeader): array
    {
        return TlSchedule::getByTeamLeader($teamLeader);
    }

    /**
     * Get all TL schedules grouped
     */
    public function getAllGrouped(): array
    {
        return TlSchedule::allGrouped();
    }

    /**
     * Get TL schedules grouped by week (for overview list)
     */
    public function getAllGroupedByWeek(): array
    {
        $weeks = TlSchedule::allGroupedByWeek();
        $result = [];
        foreach ($weeks as $week) {
            $entries = TlSchedule::getByWeek($week);
            $result[] = [
                'week_start' => $week,
                'count'      => count($entries),
                'entries'    => $entries,
            ];
        }
        return $result;
    }

    /**
     * Get TL schedules for a specific week
     */
    public function getByWeek(string $weekStart): array
    {
        return TlSchedule::getByWeek($weekStart);
    }

    /**
     * Delete all TL schedule entries for a week
     */
    public function deleteWeek(string $weekStart): bool
    {
        return TlSchedule::deleteByWeek($weekStart);
    }

    /**
     * Get TL schedule by ID
     */
    public function getById(int $id): ?array
    {
        return TlSchedule::find($id);
    }

    /**
     * Save or update a TL schedule entry
     */
    public function save(array $data): array
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Verify shift code exists
        if (!Shift::findByCode($data['shift_code'])) {
            return ['success' => false, 'errors' => ['Invalid shift code.']];
        }

        TlSchedule::upsert(
            $data['team_leader'],
            (int)$data['day_of_week'],
            $data['shift_code']
        );

        return ['success' => true];
    }

    /**
     * Delete a TL schedule entry
     */
    public function delete(int $id): bool
    {
        return TlSchedule::delete($id);
    }

    /**
     * Validate TL schedule data
     */
    private function validate(array $data): array
    {
        $errors = [];
        if (empty($data['team_leader'])) {
            $errors[] = 'Team leader is required.';
        }
        if (!isset($data['day_of_week']) || $data['day_of_week'] === '') {
            $errors[] = 'Day is required.';
        } elseif ((int)$data['day_of_week'] < 0 || (int)$data['day_of_week'] > 6) {
            $errors[] = 'Day must be between 0 (Monday) and 6 (Sunday).';
        }
        if (empty($data['shift_code'])) {
            $errors[] = 'Shift code is required.';
        }
        return $errors;
    }

    /**
     * Bulk import TL schedules from tab-separated data
     * Expected: 9 columns: TL Email, Domain, Mon, Tue, Wed, Thu, Fri, Sat, Sun
     */
    public function importBulk(string $rawText, string $weekStart): array
    {
        $lines = explode("\n", trim($rawText));
        if (empty($lines)) {
            return ['success' => false, 'errors' => ['No data provided.']];
        }

        $dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        $saved = 0;
        $errors = [];

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $values = @str_getcsv($line, "\t", '"', '');
            $values = array_map('trim', $values);

            if (count($values) < 2) continue;

            $tlEmail = $values[0];
            if (empty($tlEmail)) continue;

            // Lookup employee by email to get TL name
            $employee = Employee::findByEmail($tlEmail);
            $tlName = $employee ? $employee['name'] : $tlEmail;

            // Shift codes start at index 2 (after TL Email and Domain)
            for ($d = 0; $d < 7; $d++) {
                $shiftCode = $values[$d + 2] ?? '';
                if (empty($shiftCode) || $shiftCode === 'OFF') continue;

                // Validate shift code
                $shift = Shift::findByCode($shiftCode);
                if (!$shift) {
                    $day = $dayNames[$d] ?? "Day $d";
                    $errors[] = "Row '" . htmlspecialchars($tlEmail) . "', $day: Invalid shift '$shiftCode'";
                    continue;
                }

                try {
                    TlSchedule::upsert($tlName, $d, $shiftCode, $weekStart);
                    $saved++;
                } catch (\Exception $e) {
                    $errors[] = "Row '" . htmlspecialchars($tlEmail) . "': " . $e->getMessage();
                }
            }
        }

        return [
            'success' => $saved > 0,
            'saved' => $saved,
            'errors' => $errors
        ];
    }

    /**
     * Get all team leaders (from employees)
     */
    public function getTeamLeaders(): array
    {
        return Employee::getTeamLeaders();
    }

    /**
     * Get all shift codes
     */
    public function getShiftCodes(): array
    {
        return Shift::getAllCodes();
    }

    /**
     * Get all TL emails for lookup
     */
    public function getTeamLeaderEmails(): array
    {
        $all = Employee::all();
        $emails = [];
        foreach ($all as $emp) {
            if (!empty($emp['team_leader_email'])) {
                $emails[] = $emp['team_leader_email'];
            }
        }
        return array_unique($emails);
    }
}

<?php

namespace Services;

use Models\Employee;

class StaffService
{
    /**
     * Get all active employees
     */
    public function getAllActive(): array
    {
        return Employee::allActive();
    }

    /**
     * Get all employees
     */
    public function getAll(): array
    {
        return Employee::all();
    }

    /**
     * Get employee by ID
     */
    public function getById(int $id): ?array
    {
        return Employee::find($id);
    }

    /**
     * Create a new employee
     */
    public function create(array $data): array
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check duplicate employee_id
        if (Employee::findByEmployeeId($data['employee_id'])) {
            return ['success' => false, 'errors' => ['Employee ID already exists.']];
        }

        // Check duplicate email
        if (Employee::findByEmail($data['email'])) {
            return ['success' => false, 'errors' => ['Email already exists.']];
        }

        Employee::create($data);
        return ['success' => true];
    }

    /**
     * Update an existing employee
     */
    public function update(int $id, array $data): array
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $existing = Employee::findByEmployeeId($data['employee_id']);
        if ($existing && $existing['id'] !== $id) {
            return ['success' => false, 'errors' => ['Employee ID already in use.']];
        }

        $existingEmail = Employee::findByEmail($data['email']);
        if ($existingEmail && $existingEmail['id'] !== $id) {
            return ['success' => false, 'errors' => ['Email already in use.']];
        }

        Employee::update($id, $data);
        return ['success' => true];
    }

    /**
     * Delete an employee
     */
    public function delete(int $id): bool
    {
        return Employee::delete($id);
    }

    /**
     * Validate employee data
     */
    private function validate(array $data): array
    {
        $errors = [];
        if (empty($data['employee_id'])) {
            $errors[] = 'Employee ID is required.';
        }
        if (empty($data['name'])) {
            $errors[] = 'Employee name is required.';
        }
        if (empty($data['email'])) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        }
        if (empty($data['gender']) || !in_array($data['gender'], ['Male', 'Female'])) {
            $errors[] = 'Gender must be Male or Female.';
        }
        return $errors;
    }

    /**
     * Get all team leaders
     */
    public function getTeamLeaders(): array
    {
        return Employee::getTeamLeaders();
    }

    /**
     * Get employees by team leader
     */
    public function getByTeamLeader(string $teamLeader): array
    {
        return Employee::getByTeamLeader($teamLeader);
    }

    /**
     * Get female employees
     */
    public function getFemale(): array
    {
        return Employee::getFemale();
    }

    /**
     * Get employees grouped by compo (compo_month + compo_year)
     */
    public function getAllGroupedByCompo(): array
    {
        $all = Employee::all();
        $grouped = [];

        foreach ($all as $emp) {
            $key = ($emp['compo_month'] ?? '') . ' ' . ($emp['compo_year'] ?? '');
            if (empty(trim($key))) {
                $key = 'No Compo';
            }
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'compo_month' => $emp['compo_month'] ?? '',
                    'compo_year'  => $emp['compo_year'] ?? '',
                    'employees'   => [],
                ];
            }
            $grouped[$key]['employees'][] = $emp;
        }

        // Sort by most recent first
        uksort($grouped, function($a, $b) {
            if ($a === 'No Compo') return 1;
            if ($b === 'No Compo') return -1;
            return strcmp($b, $a); // descending
        });

        return $grouped;
    }

    /**
     * Delete all employees with given compo month and year
     */
    public function deleteByCompo(string $month, string $year): array
    {
        $db = \Database::getInstance();
        try {
            $stmt = $db->prepare('DELETE FROM employees WHERE compo_month = ? AND compo_year = ?');
            $stmt->execute([$month, $year]);
            $deleted = $stmt->rowCount();
            return ['success' => true, 'deleted' => $deleted];
        } catch (\PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Predefined column order (headers are locked — user only pastes data rows)
     * Index => field name
     */
    private array $columnOrder = [
        0  => 'employee_id',
        1  => 'name',
        2  => 'email',
        3  => 'position',
        4  => 'lob',
        5  => 'team_leader_email',
        6  => 'operational_mgr',
        7  => 'gender',
        8  => 'domain',
        9  => 'compo_month',
        10 => 'compo_year',
    ];

    /**
     * Parse tab-separated import data (headers are LOCKED, user pastes data rows only)
     */
    public function parseImportData(string $rawText): array
    {
        $lines = explode("\n", trim($rawText));
        if (empty($lines)) {
            return ['success' => false, 'errors' => ['No data rows found.']];
        }

        $rows = [];

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Use escape: '' to suppress PHP 8.4+ deprecation
            $values = @str_getcsv($line, "\t", '"', '');
            $values = array_map('trim', $values);

            $row = [];
            foreach ($this->columnOrder as $idx => $field) {
                $row[$field] = $values[$idx] ?? '';
            }

            // Skip completely empty rows
            if (empty($row['employee_id']) && empty($row['name'])) {
                continue;
            }

            $rows[] = $row;
        }

        if (empty($rows)) {
            return ['success' => false, 'errors' => ['No valid data rows found after parsing.']];
        }

        // Check for duplicate compo (month + year) across all rows
        $compoSeen = [];
        foreach ($rows as $i => $row) {
            $key = ($row['compo_month'] ?? '') . '|' . ($row['compo_year'] ?? '');
            if ($key !== '|') {
                if (isset($compoSeen[$key])) {
                    $rows[$i]['_dup_compo'] = true;
                }
                $compoSeen[$key] = true;
            }
        }

        return ['success' => true, 'rows' => $rows];
    }

    /**
     * Check if a compo (month+year) already exists in database
     */
    public function compoExists(string $month, string $year): bool
    {
        $emps = Employee::all();
        foreach ($emps as $emp) {
            if (($emp['compo_month'] ?? '') === $month && ($emp['compo_year'] ?? '') === $year) {
                return true;
            }
        }
        return false;
    }

    /**
     * Bulk import employees from parsed row data
     * Checks for duplicate compo before importing
     */
    public function bulkImport(array $rows): array
    {
        // Check if any row has compo that already exists in DB
        $blockedMonths = [];
        foreach ($rows as $i => $row) {
            $month = $row['compo_month'] ?? '';
            $year  = $row['compo_year'] ?? '';
            if ($month && $year && $this->compoExists($month, $year)) {
                $key = $month . ' ' . $year;
                if (!isset($blockedMonths[$key])) {
                    $blockedMonths[$key] = true;
                }
            }
        }

        if (!empty($blockedMonths)) {
            return [
                'success' => false,
                'created' => 0,
                'errors' => ['Duplicate compo detected: ' . implode(', ', array_keys($blockedMonths)) . '. Data for these months already exists.']
            ];
        }

        return Employee::bulkImport($rows);
    }

    /**
     * Get import column headers info
     */
    public function getImportColumns(): array
    {
        return [
            0  => 'employee_id',
            1  => 'name',
            2  => 'email',
            3  => 'position',
            4  => 'lob',
            5  => 'team_leader_email',
            6  => 'operational_mgr',
            7  => 'gender',
            8  => 'domain',
            9  => 'compo_month',
            10 => 'compo_year',
        ];
    }

    /**
     * Get locked column order for display
     */
    public function getColumnOrder(): array
    {
        return $this->columnOrder;
    }
}

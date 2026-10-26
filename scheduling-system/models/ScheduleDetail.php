<?php

namespace Models;

use Config\Database;

class ScheduleDetail
{
    /**
     * Get all details for a given schedule header
     */
    public static function getByHeader(int $scheduleHeaderId): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT sd.*, e.name AS employee_name, e.employee_id AS emp_id,
                    e.gender, e.team_leader,
                    s.start_time, s.end_time, s.category
             FROM schedule_details sd
             JOIN employees e ON e.id = sd.employee_id
             LEFT JOIN shifts s ON s.shift_code = sd.shift_code
             WHERE sd.schedule_header_id = ?
             ORDER BY e.name, sd.day_of_week'
        );
        $stmt->execute([$scheduleHeaderId]);
        return $stmt->fetchAll();
    }

    /**
     * Get schedule grouped by employee: [employee_id][day_of_week] = shift_code
     */
    public static function getGroupedByEmployee(int $scheduleHeaderId): array
    {
        $rows = self::getByHeader($scheduleHeaderId);
        $grouped = [];
        foreach ($rows as $row) {
            $empId = $row['employee_id'];
            $grouped[$empId]['name'] = $row['employee_name'];
            $grouped[$empId]['emp_id'] = $row['emp_id'];
            $grouped[$empId]['gender'] = $row['gender'];
            $grouped[$empId]['team_leader'] = $row['team_leader'];
            $grouped[$empId]['shifts'][(int)$row['day_of_week']] = $row['shift_code'];
        }
        return $grouped;
    }

    /**
     * Insert a schedule detail row
     */
    public static function create(array $data): bool
    {
        $stmt = Database::getInstance()->prepare(
            'INSERT INTO schedule_details (schedule_header_id, employee_id, day_of_week, shift_code, work_date)
             VALUES (?, ?, ?, ?, ?)'
        );
        return $stmt->execute([
            $data['schedule_header_id'],
            $data['employee_id'],
            $data['day_of_week'],
            $data['shift_code'],
            $data['work_date'],
        ]);
    }

    /**
     * Bulk insert schedule details within a transaction
     */
    public static function bulkCreate(array $rows): bool
    {
        if (empty($rows)) {
            return false;
        }

        $db = Database::getInstance();
        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                'INSERT INTO schedule_details (schedule_header_id, employee_id, day_of_week, shift_code, work_date)
                 VALUES (?, ?, ?, ?, ?)'
            );

            foreach ($rows as $row) {
                $stmt->execute([
                    $row['schedule_header_id'],
                    $row['employee_id'],
                    $row['day_of_week'],
                    $row['shift_code'],
                    $row['work_date'],
                ]);
            }

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Delete all details for a schedule header
     */
    public static function deleteByHeader(int $scheduleHeaderId): bool
    {
        $stmt = Database::getInstance()->prepare('DELETE FROM schedule_details WHERE schedule_header_id = ?');
        return $stmt->execute([$scheduleHeaderId]);
    }
}

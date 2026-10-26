<?php

namespace Models;

use Config\Database;

class LeaveRequest
{
    /**
     * Get all leave requests
     */
    public static function all(): array
    {
        $stmt = Database::getInstance()->query(
            'SELECT lr.*, e.name AS employee_name, e.employee_id AS emp_id
             FROM leave_requests lr
             JOIN employees e ON e.id = lr.employee_id
             ORDER BY lr.request_date DESC'
        );
        return $stmt->fetchAll();
    }

    /**
     * Get approved leave requests for a given week
     */
    public static function getApprovedForWeek(string $weekStart, string $weekEnd): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT lr.*, e.name AS employee_name, e.employee_id AS emp_id, e.gender
             FROM leave_requests lr
             JOIN employees e ON e.id = lr.employee_id
             WHERE lr.status = "Approved"
               AND lr.request_date BETWEEN ? AND ?
             ORDER BY lr.request_date'
        );
        $stmt->execute([$weekStart, $weekEnd]);
        return $stmt->fetchAll();
    }

    /**
     * Find by ID
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM leave_requests WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Create a new leave request
     */
    public static function create(array $data): bool
    {
        $stmt = Database::getInstance()->prepare(
            'INSERT INTO leave_requests (employee_id, request_date, reason, status)
             VALUES (?, ?, ?, ?)'
        );
        return $stmt->execute([
            $data['employee_id'],
            $data['request_date'],
            $data['reason'] ?? null,
            $data['status'] ?? 'Pending',
        ]);
    }

    /**
     * Update leave request status
     */
    public static function updateStatus(int $id, string $status): bool
    {
        $stmt = Database::getInstance()->prepare('UPDATE leave_requests SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    /**
     * Delete a leave request
     */
    public static function delete(int $id): bool
    {
        $stmt = Database::getInstance()->prepare('DELETE FROM leave_requests WHERE id = ?');
        return $stmt->execute([$id]);
    }
}

<?php

namespace Models;

use Config\Database;
use PDO;

class NotebookTask
{
    /**
     * Get all tasks for a specific user, ordered by due_date ASC, due_time ASC.
     */
    public static function allByUser(int $userId): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM notebook_tasks WHERE user_id = ? ORDER BY due_date ASC, (due_time IS NULL), due_time ASC, id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get tasks for a specific user within a date range (for calendar view).
     */
    public static function getByUserAndRange(int $userId, string $startDate, string $endDate): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM notebook_tasks 
             WHERE user_id = ? AND due_date BETWEEN ? AND ? 
             ORDER BY due_date ASC, (due_time IS NULL), due_time ASC'
        );
        $stmt->execute([$userId, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a task by id ensuring it belongs to the given user.
     */
    public static function findByUser(int $id, int $userId): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM notebook_tasks WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create a new task for user.
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO notebook_tasks (user_id, title, notes, due_date, due_time, priority, status, color)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'],
            $data['title'],
            $data['notes'] ?? null,
            $data['due_date'],
            !empty($data['due_time']) ? $data['due_time'] : null,
            $data['priority'] ?? 'Medium',
            $data['status'] ?? 'Todo',
            $data['color'] ?? '#2563eb',
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Update an existing task ensuring user ownership.
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE notebook_tasks 
             SET title = ?, notes = ?, due_date = ?, due_time = ?, priority = ?, status = ?, color = ?
             WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([
            $data['title'],
            $data['notes'] ?? null,
            $data['due_date'],
            !empty($data['due_time']) ? $data['due_time'] : null,
            $data['priority'] ?? 'Medium',
            $data['status'] ?? 'Todo',
            $data['color'] ?? '#2563eb',
            $id,
            $userId,
        ]);
    }

    /**
     * Toggle or update only status.
     */
    public static function updateStatus(int $id, int $userId, string $status): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE notebook_tasks SET status = ? WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([$status, $id, $userId]);
    }

    /**
     * Delete a task ensuring user ownership.
     */
    public static function delete(int $id, int $userId): bool
    {
        $stmt = Database::getInstance()->prepare(
            'DELETE FROM notebook_tasks WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([$id, $userId]);
    }
}


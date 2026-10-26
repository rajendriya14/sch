<?php

namespace Models;

use Config\Database;

class ScheduleHeader
{
    /**
     * Get all schedule headers, most recent first
     */
    public static function all(): array
    {
        $stmt = Database::getInstance()->query(
            'SELECT * FROM schedule_headers ORDER BY generated_at DESC'
        );
        return $stmt->fetchAll();
    }

    /**
     * Find by ID
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM schedule_headers WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get the latest schedule header
     */
    public static function getLatest(): ?array
    {
        $stmt = Database::getInstance()->query(
            'SELECT * FROM schedule_headers ORDER BY generated_at DESC LIMIT 1'
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get the latest schedule for a given week
     */
    public static function getLatestForWeek(string $weekStartDate): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM schedule_headers WHERE week_start_date = ? ORDER BY generated_at DESC LIMIT 1'
        );
        $stmt->execute([$weekStartDate]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Create a new schedule header
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO schedule_headers (week_start_date, status) VALUES (?, ?)'
        );
        $stmt->execute([
            $data['week_start_date'],
            $data['status'] ?? 'Draft',
        ]);
        return (int)$db->lastInsertId();
    }

    /**
     * Update schedule status
     */
    public static function updateStatus(int $id, string $status): bool
    {
        $stmt = Database::getInstance()->prepare('UPDATE schedule_headers SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    /**
     * Delete a schedule header and all its details (cascade)
     */
    public static function delete(int $id): bool
    {
        $stmt = Database::getInstance()->prepare('DELETE FROM schedule_headers WHERE id = ?');
        return $stmt->execute([$id]);
    }
}

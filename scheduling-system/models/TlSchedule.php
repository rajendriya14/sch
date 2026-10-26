<?php

namespace Models;

use Config\Database;

class TlSchedule
{
    /**
     * Get all TL schedules with shift details
     */
    public static function all(): array
    {
        $stmt = Database::getInstance()->query(
            'SELECT tls.*, s.start_time, s.end_time, s.category
             FROM tl_schedules tls
             JOIN shifts s ON s.shift_code = tls.shift_code
             ORDER BY tls.team_leader, tls.day_of_week'
        );
        return $stmt->fetchAll();
    }

    /**
     * Get schedule for a specific team leader
     */
    public static function getByTeamLeader(string $teamLeader): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT tls.*, s.start_time, s.end_time, s.category
             FROM tl_schedules tls
             JOIN shifts s ON s.shift_code = tls.shift_code
             WHERE tls.team_leader = ?
             ORDER BY tls.day_of_week'
        );
        $stmt->execute([$teamLeader]);
        return $stmt->fetchAll();
    }

    /**
     * Get all TL schedules grouped: [team_leader][day_of_week] = shift_code
     */
    public static function allGrouped(): array
    {
        $rows = self::all();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['team_leader']][(int)$row['day_of_week']] = $row['shift_code'];
        }
        return $grouped;
    }

    /**
     * Find a single TL schedule record
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM tl_schedules WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all TL schedules grouped by week_start_date
     */
    public static function allGroupedByWeek(): array
    {
        $stmt = Database::getInstance()->query(
            'SELECT DISTINCT week_start_date FROM tl_schedules
             WHERE week_start_date IS NOT NULL
             ORDER BY week_start_date DESC'
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get TL schedules for a specific week
     */
    public static function getByWeek(string $weekStartDate): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT tls.*, s.start_time, s.end_time, s.category
             FROM tl_schedules tls
             JOIN shifts s ON s.shift_code = tls.shift_code
             WHERE tls.week_start_date = ?
             ORDER BY tls.team_leader, tls.day_of_week'
        );
        $stmt->execute([$weekStartDate]);
        return $stmt->fetchAll();
    }

    /**
     * Delete all TL schedule entries for a week
     */
    public static function deleteByWeek(string $weekStartDate): bool
    {
        $stmt = Database::getInstance()->prepare('DELETE FROM tl_schedules WHERE week_start_date = ?');
        return $stmt->execute([$weekStartDate]);
    }

    /**
     * Create or update a TL schedule entry
     */
    public static function upsert(string $teamLeader, int $dayOfWeek, string $shiftCode, ?string $weekStart = null): bool
    {
        $db = Database::getInstance();
        if ($weekStart) {
            $stmt = $db->prepare(
                'INSERT INTO tl_schedules (team_leader, day_of_week, shift_code, week_start_date)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE shift_code = VALUES(shift_code), week_start_date = VALUES(week_start_date)'
            );
            return $stmt->execute([$teamLeader, $dayOfWeek, $shiftCode, $weekStart]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO tl_schedules (team_leader, day_of_week, shift_code)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE shift_code = VALUES(shift_code)'
            );
            return $stmt->execute([$teamLeader, $dayOfWeek, $shiftCode]);
        }
    }

    /**
     * Delete a TL schedule entry
     */
    public static function delete(int $id): bool
    {
        $stmt = Database::getInstance()->prepare('DELETE FROM tl_schedules WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Delete by team leader and day
     */
    public static function deleteByTlAndDay(string $teamLeader, int $dayOfWeek): bool
    {
        $stmt = Database::getInstance()->prepare(
            'DELETE FROM tl_schedules WHERE team_leader = ? AND day_of_week = ?'
        );
        return $stmt->execute([$teamLeader, $dayOfWeek]);
    }
}

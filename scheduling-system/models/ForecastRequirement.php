<?php

namespace Models;

use Config\Database;

class ForecastRequirement
{
    /**
     * Get all forecast requirements, keyed by day_of_week + hour
     */
    public static function all(): array
    {
        $stmt = Database::getInstance()->query(
            'SELECT * FROM forecast_requirements ORDER BY day_of_week, hour'
        );
        return $stmt->fetchAll();
    }

    /**
     * Get grouped forecast: [day_of_week][hour] = required_count
     */
    public static function allGrouped(): array
    {
        $rows = self::all();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['day_of_week']][(int)$row['hour']] = (int)$row['required_count'];
        }
        return $grouped;
    }

    /**
     * Get forecast for a specific day
     */
    public static function getByDay(int $dayOfWeek): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM forecast_requirements WHERE day_of_week = ? ORDER BY hour'
        );
        $stmt->execute([$dayOfWeek]);
        return $stmt->fetchAll();
    }

    /**
     * Get a single forecast requirement
     */
    public static function find(int $dayOfWeek, int $hour): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM forecast_requirements WHERE day_of_week = ? AND hour = ?'
        );
        $stmt->execute([$dayOfWeek, $hour]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Upsert a forecast requirement (insert or update)
     */
    public static function upsert(int $dayOfWeek, int $hour, int $requiredCount): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO forecast_requirements (day_of_week, hour, required_count)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE required_count = VALUES(required_count)'
        );
        return $stmt->execute([$dayOfWeek, $hour, $requiredCount]);
    }

    /**
     * Get total required headcount for all days
     */
    public static function getTotalRequirements(): int
    {
        $stmt = Database::getInstance()->query('SELECT SUM(required_count) AS total FROM forecast_requirements');
        $row = $stmt->fetch();
        return (int)($row['total'] ?? 0);
    }
}

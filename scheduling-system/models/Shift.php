<?php
// v2026.08.13.2 — getInactiveCodes reads is_active; getActive checks is_active=1
namespace Models;

use Config\Database;
use PDO;

class Shift
{
    public static function getAll(): array
    {
        $db = Database::getInstance();
        return $db->query("SELECT * FROM shifts ORDER BY FIELD(category,'Morning','Swing','Night','Leave'), start_time, shift_code")->fetchAll();
    }

    public static function getActive(): array
    {
        $db = Database::getInstance();
        try {
            // Active = is_active = 1 AND not in shift_inactive table
            return $db->query(
                "SELECT s.* FROM shifts s
                 LEFT JOIN shift_inactive si ON si.shift_code = s.shift_code
                 WHERE si.shift_code IS NULL AND s.is_active = 1
                 ORDER BY FIELD(category,'Morning','Swing','Night','Leave'), start_time, shift_code"
            )->fetchAll();
        } catch (\PDOException $e) {
            // shift_inactive table might not exist — use is_active only
            try {
                return $db->query(
                    "SELECT * FROM shifts WHERE is_active = 1 ORDER BY FIELD(category,'Morning','Swing','Night','Leave'), start_time, shift_code"
                )->fetchAll();
            } catch (\PDOException $e2) {
                // is_active column might not exist either — return all shifts
                return $db->query("SELECT * FROM shifts ORDER BY FIELD(category,'Morning','Swing','Night','Leave'), start_time, shift_code")->fetchAll();
            }
        }
    }

    public static function getLeaveTypes(): array
    {
        $db = Database::getInstance();
        return $db->query("SELECT * FROM shifts WHERE category = 'Leave' ORDER BY shift_code")->fetchAll();
    }

    public static function findByCode(string $code): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM shifts WHERE shift_code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM shifts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO shifts (shift_code, start_time, end_time, category, description) VALUES (?,?,?,?,?)");
        return $stmt->execute([$data['shift_code'], $data['start_time'], $data['end_time'], $data['category'], $data['description'] ?? null]);
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE shifts SET shift_code=?, start_time=?, end_time=?, category=?, description=? WHERE id=?");
        return $stmt->execute([$data['shift_code'], $data['start_time'], $data['end_time'], $data['category'], $data['description'] ?? null, $id]);
    }

    public static function delete(int $id): bool
    {
        $db = Database::getInstance();
        return $db->prepare("DELETE FROM shifts WHERE id=?")->execute([$id]);
    }

    public static function getInactiveCodes(): array
    {
        $db = Database::getInstance();
        try {
            // Primary: read from is_active column
            return $db->query("SELECT shift_code FROM shifts WHERE is_active = 0")->fetchAll(PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            // Fallback: shift_inactive table
            try {
                return $db->query("SELECT shift_code FROM shift_inactive")->fetchAll(PDO::FETCH_COLUMN);
            } catch (\PDOException $e2) {
                return [];
            }
        }
    }

    public static function getFemaleRestrictedCodes(): array
    {
        $db = Database::getInstance();
        try {
            return $db->query("SELECT shift_code FROM female_restricted_shifts")->fetchAll(PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Set whether a shift is female-restricted or not
     */
    public static function setFemaleRestricted(string $code, bool $restricted = true): void
    {
        $db = Database::getInstance();
        try {
            if ($restricted) {
                $db->prepare("INSERT IGNORE INTO female_restricted_shifts (shift_code) VALUES (?)")->execute([$code]);
            } else {
                $db->prepare("DELETE FROM female_restricted_shifts WHERE shift_code = ?")->execute([$code]);
            }
        } catch (\PDOException $e) {
            // Table might not exist — ignore
        }
    }

    public static function toggleActive(string $code, bool $active = true): void
    {
        $db = Database::getInstance();
        try {
            // Get current is_active status
            $stmt = $db->prepare("SELECT is_active FROM shifts WHERE shift_code = ?");
            $stmt->execute([$code]);
            $current = (int)$stmt->fetchColumn();
            $newValue = $active ? 1 : 0;
            
            $stmt = $db->prepare("UPDATE shifts SET is_active = ? WHERE shift_code = ?");
            $stmt->execute([$newValue, $code]);
        } catch (\PDOException $e) {
            // Fallback: use shift_inactive table
            if ($active) {
                $db->prepare("DELETE FROM shift_inactive WHERE shift_code = ?")->execute([$code]);
            } else {
                $db->prepare("INSERT IGNORE INTO shift_inactive (shift_code) VALUES (?)")->execute([$code]);
            }
        }
    }

    public static function getByCategory(string $category): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM shifts WHERE category = ? ORDER BY start_time");
        $stmt->execute([$category]);
        return $stmt->fetchAll();
    }

    public static function getAllCodes(): array
    {
        $db = Database::getInstance();
        return $db->query("SELECT shift_code FROM shifts ORDER BY start_time")->fetchAll(PDO::FETCH_COLUMN);
    }
}

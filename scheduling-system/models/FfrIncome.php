<?php
// Pemasukan bulanan (gaji, freelance, bonus).
namespace Models;

use Config\Database;
use PDO;

class FfrIncome
{
    public static function byPeriod(int $periodId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM ffr_incomes WHERE period_id = ? ORDER BY id');
        $stmt->execute([$periodId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function total(int $periodId): float
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COALESCE(SUM(amount), 0) FROM ffr_incomes WHERE period_id = ?');
        $stmt->execute([$periodId]);
        return (float) $stmt->fetchColumn();
    }

    public static function add(int $periodId, string $source, float $amount, string $owner = 'Bersama'): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('INSERT INTO ffr_incomes (period_id, source, amount, owner) VALUES (?,?,?,?)');
        $stmt->execute([$periodId, trim($source), $amount, $owner]);
        return (int) $db->lastInsertId();
    }

    public static function delete(int $id): bool
    {
        $db = Database::getInstance();
        return $db->prepare('DELETE FROM ffr_incomes WHERE id = ?')->execute([$id]);
    }

    public static function clearPeriod(int $periodId): void
    {
        $db = Database::getInstance();
        $db->prepare('DELETE FROM ffr_incomes WHERE period_id = ?')->execute([$periodId]);
    }
}

<?php
// Periode bulanan FFR ('YYYY-MM') — status buka/tutup buku.
namespace Models;

use Config\Database;
use PDO;

class FfrPeriod
{
    public static function all(): array
    {
        $db = Database::getInstance();
        return $db->query('SELECT * FROM ffr_periods ORDER BY period DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM ffr_periods WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findByPeriod(string $period): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM ffr_periods WHERE period = ?');
        $stmt->execute([$period]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Ambil periode, buat bila belum ada. Operator keuangan memakai ini
     * supaya bulan baru langsung siap dipakai tanpa langkah setup terpisah.
     */
    public static function findOrCreate(string $period): array
    {
        $existing = self::findByPeriod($period);
        if ($existing) {
            return $existing;
        }

        $db = Database::getInstance();
        $db->prepare('INSERT IGNORE INTO ffr_periods (period) VALUES (?)')->execute([$period]);

        return self::findByPeriod($period) ?? ['id' => 0, 'period' => $period, 'status' => 'open'];
    }

    public static function setStatus(int $id, string $status): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE ffr_periods SET status = ? WHERE id = ?');
        return $stmt->execute([$status === 'closed' ? 'closed' : 'open', $id]);
    }

    public static function saveNotes(int $id, string $notes): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE ffr_periods SET notes = ? WHERE id = ?');
        return $stmt->execute([trim($notes) ?: null, $id]);
    }

    public static function delete(int $id): bool
    {
        $db = Database::getInstance();
        return $db->prepare('DELETE FROM ffr_periods WHERE id = ?')->execute([$id]);
    }

    /**
     * Daftar bulan terakhir (descending) — untuk pemilih periode.
     */
    public static function recent(int $months = 18): array
    {
        $out = [];
        $now = new \DateTimeImmutable('first day of this month');
        for ($i = 0; $i < $months; $i++) {
            $out[] = $now->modify('-' . $i . ' month')->format('Y-m');
        }
        return $out;
    }
}

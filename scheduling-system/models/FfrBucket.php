<?php
// Bucket alokasi dana: Utama (wajib) / Tambahan (opsional) / Tabungan / Dana Darurat.
namespace Models;

use Config\Database;
use PDO;

class FfrBucket
{
    public static function all(bool $activeOnly = true): array
    {
        $db = Database::getInstance();
        $sql = 'SELECT * FROM ffr_buckets';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        return $db->query($sql . ' ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM ffr_buckets WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findByCode(string $code): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM ffr_buckets WHERE code = ?');
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO ffr_buckets (code, name, type, description, sort_order) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([
            strtoupper(trim($data['code'])),
            trim($data['name']),
            $data['type'] ?? 'wajib',
            trim($data['description'] ?? '') ?: null,
            (int) ($data['sort_order'] ?? 0),
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE ffr_buckets SET name = ?, type = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?'
        );
        return $stmt->execute([
            trim($data['name']),
            $data['type'] ?? 'wajib',
            trim($data['description'] ?? '') ?: null,
            (int) ($data['sort_order'] ?? 0),
            !empty($data['is_active']) ? 1 : 0,
            $id,
        ]);
    }

    /**
     * Hapus bucket. Ditolak bila masih punya item di periode mana pun,
     * supaya data rencana tidak hilang tanpa disadari.
     */
    public static function delete(int $id): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM ffr_items WHERE bucket_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['success' => false, 'error' => 'Bucket masih punya item belanja. Hapus itemnya dulu.'];
        }
        $db->prepare('DELETE FROM ffr_buckets WHERE id = ?')->execute([$id]);
        return ['success' => true, 'error' => ''];
    }
}

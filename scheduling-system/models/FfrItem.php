<?php
// Item belanja di dalam bucket — satu baris = satu item (Odol, Cabe, Bensin, …).
namespace Models;

use Config\Database;
use PDO;

class FfrItem
{
    /** Berapa kali beli per bulan untuk tiap frekuensi. */
    public const FREQ_MULTIPLIER = [
        'weekly'   => 4,
        'biweekly' => 2,
        'monthly'  => 1,
        'once'     => 0,
    ];

    public const FREQ_LABEL = [
        'weekly'   => '1 minggu',
        'biweekly' => '2 minggu',
        'monthly'  => '1 bulan',
        'once'     => 'sekali',
    ];

    public static function byPeriod(int $periodId, ?int $bucketId = null): array
    {
        $db = Database::getInstance();
        $sql = 'SELECT i.*, b.code AS bucket_code, b.name AS bucket_name, b.type AS bucket_type,
                       c.name AS category_name
                FROM ffr_items i
                JOIN ffr_buckets b ON b.id = i.bucket_id
                LEFT JOIN ffr_categories c ON c.id = i.category_id
                WHERE i.period_id = ?';
        $params = [$periodId];

        if ($bucketId !== null) {
            $sql .= ' AND i.bucket_id = ?';
            $params[] = $bucketId;
        }

        $sql .= ' ORDER BY b.sort_order, i.sort_order, i.id';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM ffr_items WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO ffr_items
                (period_id, bucket_id, category_id, name, freq_type, unit_price, qty, actual_amount, is_priority, notes, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            (int) $data['period_id'],
            (int) $data['bucket_id'],
            !empty($data['category_id']) ? (int) $data['category_id'] : null,
            trim($data['name']),
            $data['freq_type'] ?? 'monthly',
            (float) ($data['unit_price'] ?? 0),
            (float) ($data['qty'] ?? 1),
            ($data['actual_amount'] === null || $data['actual_amount'] === '') ? null : (float) $data['actual_amount'],
            !empty($data['is_priority']) ? 1 : 0,
            trim($data['notes'] ?? '') ?: null,
            (int) ($data['sort_order'] ?? 0),
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE ffr_items
             SET bucket_id = ?, category_id = ?, name = ?, freq_type = ?, unit_price = ?, qty = ?,
                 actual_amount = ?, is_priority = ?, notes = ?, sort_order = ?
             WHERE id = ?'
        );
        return $stmt->execute([
            (int) $data['bucket_id'],
            !empty($data['category_id']) ? (int) $data['category_id'] : null,
            trim($data['name']),
            $data['freq_type'] ?? 'monthly',
            (float) ($data['unit_price'] ?? 0),
            (float) ($data['qty'] ?? 1),
            ($data['actual_amount'] === null || $data['actual_amount'] === '') ? null : (float) $data['actual_amount'],
            !empty($data['is_priority']) ? 1 : 0,
            trim($data['notes'] ?? '') ?: null,
            (int) ($data['sort_order'] ?? 0),
            $id,
        ]);
    }

    public static function delete(int $id): bool
    {
        $db = Database::getInstance();
        return $db->prepare('DELETE FROM ffr_items WHERE id = ?')->execute([$id]);
    }

    /**
     * Salin semua item dari periode sumber ke periode tujuan.
     */
    public static function copyFrom(int $fromPeriodId, int $toPeriodId): int
    {
        $rows = self::byPeriod($fromPeriodId);
        $count = 0;
        foreach ($rows as $row) {
            self::create([
                'period_id'     => $toPeriodId,
                'bucket_id'     => (int) $row['bucket_id'],
                'category_id'   => $row['category_id'],
                'name'          => $row['name'],
                'freq_type'     => $row['freq_type'],
                'unit_price'    => $row['unit_price'],
                'qty'           => $row['qty'],
                'actual_amount' => null,
                'is_priority'   => $row['is_priority'],
                'notes'         => $row['notes'],
                'sort_order'    => $row['sort_order'],
            ]);
            $count++;
        }
        return $count;
    }
}

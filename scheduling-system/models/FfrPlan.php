<?php
// Alokasi dana per bucket untuk satu periode.
namespace Models;

use Config\Database;
use PDO;

class FfrPlan
{
    /**
     * Peta bucket_id => amount untuk satu periode.
     */
    public static function mapByPeriod(int $periodId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT bucket_id, amount FROM ffr_plans WHERE period_id = ?');
        $stmt->execute([$periodId]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['bucket_id']] = (float) $row['amount'];
        }
        return $out;
    }

    /**
     * Peta bucket_id => ['amount' => float, 'percent' => ?float, 'source' => string].
     * Dipakai layar Rencana Bulan yang mendukung input rupiah maupun persen.
     */
    public static function mapAllocation(int $periodId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT bucket_id, amount, percent, source FROM ffr_plans WHERE period_id = ?');
        $stmt->execute([$periodId]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $percent = $row['percent'] === null ? null : (float) $row['percent'];
            $out[(int) $row['bucket_id']] = [
                'amount'  => (float) $row['amount'],
                'percent' => $percent,
                'source'  => in_array($row['source'] ?? '', ['rp', 'percent'], true) ? $row['source'] : 'rp',
            ];
        }
        return $out;
    }

    public static function total(int $periodId): float
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COALESCE(SUM(amount), 0) FROM ffr_plans WHERE period_id = ?');
        $stmt->execute([$periodId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Total alokasi untuk bucket bertipe 'simpan' (tabungan + dana darurat).
     */
    public static function totalSaving(int $periodId): float
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(p.amount), 0)
             FROM ffr_plans p
             JOIN ffr_buckets b ON b.id = p.bucket_id
             WHERE p.period_id = ? AND b.type = "simpan"'
        );
        $stmt->execute([$periodId]);
        return (float) $stmt->fetchColumn();
    }

    public static function setAmount(int $periodId, int $bucketId, float $amount): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO ffr_plans (period_id, bucket_id, amount) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount)'
        );
        $stmt->execute([$periodId, $bucketId, $amount]);
    }

    /**
     * Simpan alokasi lengkap dengan informasi persen.
     *
     * $source = 'percent' berarti pengguna mengetik persen, jadi nominal rupiah
     * boleh dihitung ulang saat pemasukan berubah. $source = 'rp' berarti
     * nominal dikunci (persen hanya hasil bagi).
     */
    public static function setAllocation(
        int $periodId,
        int $bucketId,
        float $amount,
        ?float $percent = null,
        string $source = 'rp'
    ): void {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO ffr_plans (period_id, bucket_id, amount, percent, source) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount),
                                     percent = VALUES(percent),
                                     source = VALUES(source)'
        );
        $stmt->execute([
            $periodId,
            $bucketId,
            $amount,
            $percent,
            in_array($source, ['rp', 'percent'], true) ? $source : 'rp',
        ]);
    }

    /**
     * Salin seluruh alokasi dari periode sumber (dipakai tombol "Salin bulan lalu").
     *
     * Persen dan sumber input ikut disalin supaya status baris (persen vs
     * nominal terkunci) tetap konsisten di bulan tujuan.
     */
    public static function copyFrom(int $fromPeriodId, int $toPeriodId): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT bucket_id, amount, percent, source FROM ffr_plans WHERE period_id = ?');
        $stmt->execute([$fromPeriodId]);

        $count = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            self::setAllocation(
                $toPeriodId,
                (int) $row['bucket_id'],
                (float) $row['amount'],
                $row['percent'] === null ? null : (float) $row['percent'],
                (string) ($row['source'] ?? 'rp')
            );
            $count++;
        }
        return $count;
    }
}

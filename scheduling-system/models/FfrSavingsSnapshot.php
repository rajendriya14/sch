<?php

namespace Models;

use Config\Database;

/**
 * Riwayat nilai portofolio "Tabunganku".
 *
 * Satu baris = satu hari. Dipakai laman Dashboard Tabunganku untuk
 * menampilkan tren naik/turun nilai aset. Detail per kategori disimpan
 * sebagai JSON agar grafik per jenis juga bisa dibuat tanpa tabel tambahan.
 */
class FfrSavingsSnapshot
{
    /**
     * Simpan/perbarui snapshot satu tanggal.
     *
     * @param array $totals total_value, total_cost, total_gross, asset_count
     * @param array $detail type_key => nilai (Rupiah)
     */
    public static function upsert(string $date, array $totals, array $detail = []): bool
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'INSERT INTO ffr_savings_snapshots
                    (snapshot_date, total_value, total_cost, total_gross, asset_count, detail)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    total_value = VALUES(total_value),
                    total_cost  = VALUES(total_cost),
                    total_gross = VALUES(total_gross),
                    asset_count = VALUES(asset_count),
                    detail      = VALUES(detail)'
            );

            return $stmt->execute([
                $date,
                (float) ($totals['total_value'] ?? 0),
                (float) ($totals['total_cost'] ?? 0),
                (float) ($totals['total_gross'] ?? 0),
                (int) ($totals['asset_count'] ?? 0),
                $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Riwayat snapshot, terurut dari terlama ke terbaru.
     *
     * @param int $days Batasi N hari terakhir (0 = semua).
     */
    public static function all(int $days = 0): array
    {
        try {
            $db = Database::getInstance();
            $sql = 'SELECT snapshot_date, total_value, total_cost, total_gross, asset_count, detail,
                           created_at
                    FROM ffr_savings_snapshots';
            if ($days > 0) {
                // $days sudah di-cast ke int, jadi aman disisipkan langsung
                // (placeholder di dalam INTERVAL tidak didukung semua versi MySQL).
                $sql .= ' WHERE snapshot_date >= DATE_SUB(CURDATE(), INTERVAL ' . (int) $days . ' DAY)';
            }
            $sql .= ' ORDER BY snapshot_date ASC';

            $stmt = $db->query($sql);
            $rows = $stmt->fetchAll() ?: [];
        } catch (\PDOException $e) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['total_value'] = (float) $row['total_value'];
            $row['total_cost']  = (float) $row['total_cost'];
            $row['total_gross'] = (float) $row['total_gross'];
            $row['asset_count'] = (int) $row['asset_count'];
            $row['detail']      = $row['detail'] ? (json_decode($row['detail'], true) ?: []) : [];
        }
        unset($row);

        return $rows;
    }

    /** Snapshot terakhir (atau null bila belum ada). */
    public static function latest(): ?array
    {
        $rows = self::all();
        return $rows ? $rows[count($rows) - 1] : null;
    }

    /** Jumlah hari yang sudah tercatat. */
    public static function count(): int
    {
        try {
            return (int) Database::getInstance()
                ->query('SELECT COUNT(*) FROM ffr_savings_snapshots')
                ->fetchColumn();
        } catch (\PDOException $e) {
            return 0;
        }
    }
}

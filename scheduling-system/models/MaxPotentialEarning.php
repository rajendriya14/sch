<?php

namespace Models;

use Config\Database;

/**
 * Max Potential Earnings — rate card BAK.
 *
 * Satu baris memuat dua angka yang dibandingkan berdampingan:
 *   - Hasil hitung WFM : quantity (rate) x price_per_unit -> subtotal
 *   - Max Potential     : manual_amount (diisi manual, boleh kosong)
 *
 * Nilai acuan yang dipakai step berikutnya adalah manual_amount bila diisi,
 * jika tidak dipakai subtotal (lihat baseExpr()).
 */
class MaxPotentialEarning
{
    /** Ekspresi SQL nilai acuan: manual menang atas hasil hitung WFM. */
    private static function baseExpr(string $alias = ''): string
    {
        $prefix = $alias === '' ? '' : $alias . '.';
        return 'COALESCE(' . $prefix . 'manual_amount, ' . $prefix . 'subtotal)';
    }

    /** Kolom yang selalu dikirim ke frontend. */
    private static function columns(): string
    {
        return 'id, role, category, billing_type, price_per_unit, price_manual,
                quantity, subtotal, manual_amount, manual_rate, is_custom,
                ' . self::baseExpr() . ' AS base_amount';
    }

    /**
     * Semua baris rate card.
     */
    public static function all(): array
    {
        $stmt = Database::getInstance()->query(
            'SELECT ' . self::columns() . '
             FROM max_potential_earnings
             ORDER BY category, role'
        );
        return $stmt->fetchAll();
    }

    /**
     * Satu baris rate card.
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT ' . self::columns() . '
             FROM max_potential_earnings WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Cari berdasarkan nama role (untuk role yang ditambahkan manual).
     */
    public static function findByRole(string $role): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT ' . self::columns() . '
             FROM max_potential_earnings WHERE role = ?'
        );
        $stmt->execute([$role]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Tambah role baru di luar hasil hitung WFM (mis. rate card project lain).
     *
     * @throws \InvalidArgumentException bila nama role kosong atau sudah ada.
     */
    public static function create(
        string $role,
        ?float $pricePerUnit = null,
        ?float $manualAmount = null,
        ?string $billingType = null,
        ?string $category = null,
        ?float $manualRate = null
    ): array {
        $role = trim($role);
        if ($role === '') {
            throw new \InvalidArgumentException('Nama role wajib diisi.');
        }
        if (self::findByRole($role) !== null) {
            throw new \InvalidArgumentException("Role \"{$role}\" sudah ada di Max Potential Earnings.");
        }

        $price = $pricePerUnit ?? 0.0;
        $rate  = $manualRate;
        // Tanpa nominal eksplisit: nominal = rate × price/unit.
        if ($manualAmount === null && $rate !== null) {
            $manualAmount = round($price * $rate, 2);
        } elseif ($rate === null && $manualAmount !== null && $price > 0) {
            $rate = round($manualAmount / $price, 2);
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO max_potential_earnings
                (role, category, billing_type, price_per_unit, price_manual,
                 quantity, subtotal, manual_amount, manual_rate, is_custom)
             VALUES (?, ?, ?, ?, 1, 0, 0, ?, ?, 1)'
        );
        $stmt->execute([
            $role,
            $category,
            $billingType ?? 'Headcount',
            $price,
            $manualAmount === null ? null : round($manualAmount, 2),
            $rate === null ? null : round($rate, 2),
        ]);

        $created = $db->prepare(
            'SELECT ' . self::columns() . ' FROM max_potential_earnings WHERE id = ?'
        );
        $created->execute([(int) $db->lastInsertId()]);
        $row = $created->fetch();
        if ($row === false) {
            throw new \RuntimeException('Gagal menambahkan role baru.');
        }
        return $row;
    }

    /**
     * Update jenis billing, price/unit, dan nilai Max Potential manual.
     *
     * `quantity` (rate) tidak diubah di sini karena berasal dari WFM.
     * $manualAmount = false berarti nilai manual tidak disentuh; null berarti
     * nilai manual dihapus (kembali memakai hasil hitung WFM).
     *
     * @throws \RuntimeException bila baris tidak ditemukan.
     */
    public static function update(
        int $id,
        ?float $pricePerUnit,
        ?float $quantity,
        ?string $billingType = null,
        float|null|false $manualAmount = false,
        float|null|false $manualRate = false
    ): array {
        $existing = self::find($id);
        if ($existing === null) {
            throw new \RuntimeException("max_potential_earnings id {$id} tidak ditemukan.");
        }

        $price    = $pricePerUnit ?? 0.0;
        // Rate (quantity) berasal dari WFM; null berarti pertahankan nilai lama.
        $qty      = $quantity === null ? (float) ($existing['quantity'] ?? 0) : $quantity;
        $subtotal = round($price * $qty, 2);
        $billing  = $billingType !== null ? $billingType : ($existing['billing_type'] ?? null);

        // Harga hanya ditandai manual bila memang berbeda dari nilai tersimpan,
        // supaya sinkron WFM berikutnya tidak ikut terkunci.
        $priceManual = abs($price - (float) ($existing['price_per_unit'] ?? 0)) > 0.004
            ? 1
            : (int) ($existing['price_manual'] ?? 0);

        // Max Potential manual: rate × price/unit. Salah satu boleh dikirim;
        // yang lain diturunkan supaya keduanya selalu konsisten.
        $rate   = $manualRate === false ? $existing['manual_rate'] : $manualRate;
        $amount = $manualAmount;

        if ($amount === false) {
            $amount = $rate === null || $rate === false ? null : round((float) $rate * $price, 2);
        } elseif ($manualRate === false) {
            $rate = ($price > 0 && $amount !== null) ? round((float) $amount / $price, 2) : null;
        }

        $rate   = ($rate === false || $rate === null) ? null : round((float) $rate, 2);
        $amount = $amount === null ? null : round((float) $amount, 2);

        $stmt = Database::getInstance()->prepare(
            'UPDATE max_potential_earnings
                SET billing_type = ?, price_per_unit = ?, quantity = ?, subtotal = ?,
                    manual_amount = ?, manual_rate = ?, price_manual = ?
              WHERE id = ?'
        );
        $stmt->execute([$billing, $price, $qty, $subtotal, $amount, $rate, $priceManual, $id]);

        $updated = self::find($id);
        if ($updated === null) {
            throw new \RuntimeException('Gagal memperbarui max potential earnings.');
        }
        return $updated;
    }

    /**
     * Nilai acuan gabungan (manual bila diisi, kalau tidak hasil hitung WFM).
     */
    public static function baseAmount(int $id): float
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT ' . self::baseExpr() . ' AS base_amount FROM max_potential_earnings WHERE id = ?'
        );
        $stmt->execute([$id]);
        $value = $stmt->fetchColumn();
        return $value === false ? 0.0 : (float) $value;
    }

    /**
     * Hapus satu role dari rate card (baris accrual & actual ikut terhapus
     * lewat ON DELETE CASCADE).
     *
     * @throws \RuntimeException bila baris tidak ditemukan.
     */
    public static function delete(int $id): void
    {
        $existing = self::find($id);
        if ($existing === null) {
            throw new \RuntimeException("max_potential_earnings id {$id} tidak ditemukan.");
        }

        $stmt = Database::getInstance()->prepare('DELETE FROM max_potential_earnings WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Breakdown subtotal per kategori + grand total.
     *
     * @return array{categories: array<int, array{category: string, subtotal: float}>,
     *               grand_total: float, wfm_total: float, manual_total: float}
     */
    public static function summary(): array
    {
        $base = self::baseExpr();
        $sql  = "SELECT COALESCE(NULLIF(category, ''), 'Uncategorized') AS category,
                        SUM({$base}) AS subtotal,
                        SUM(subtotal) AS wfm_subtotal,
                        SUM(COALESCE(manual_amount, 0)) AS manual_subtotal
                 FROM max_potential_earnings
                 GROUP BY COALESCE(NULLIF(category, ''), 'Uncategorized')
                 ORDER BY category";

        $stmt = Database::getInstance()->query($sql);

        $categories  = [];
        $grandTotal  = 0.0;
        $wfmTotal    = 0.0;
        $manualTotal = 0.0;
        foreach ($stmt->fetchAll() as $row) {
            $subtotal    = (float) $row['subtotal'];
            $grandTotal  += $subtotal;
            $wfmTotal    += (float) $row['wfm_subtotal'];
            $manualTotal += (float) $row['manual_subtotal'];
            $categories[] = [
                'category' => (string) $row['category'],
                'subtotal' => round($subtotal, 2),
            ];
        }

        return [
            'categories'   => $categories,
            'grand_total'  => round($grandTotal, 2),
            'wfm_total'    => round($wfmTotal, 2),
            'manual_total' => round($manualTotal, 2),
        ];
    }
}

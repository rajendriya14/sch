<?php

namespace Models;

use Config\Database;

/**
 * Seller Input — nilai yang diajukan seller per baris max potential.
 *
 * Catatan integritas: `max_potential` dan `role` selalu dibaca ulang dari tabel
 * max_potential_earnings, dan variance dihitung di sini — nilai dari client
 * tidak pernah dipercaya.
 */
class SellerInput
{
    /**
     * Semua baris seller input.
     *
     * `is_locked` menandai baris yang actual_input-nya sudah LOCKED sehingga
     * nilainya tidak boleh diubah lagi dari UI.
     */
    public static function all(): array
    {
        $stmt = Database::getInstance()->query(
            "SELECT s.id, s.max_potential_id, s.role, s.max_potential, s.quantity, s.price_per_unit,
                    s.auto_quantity, s.auto_price_per_unit,
                    s.seller_input, s.variance, s.variance_pct, s.variance_reason, s.notes,
                    CASE WHEN a.status = 'LOCKED' THEN 1 ELSE 0 END AS is_locked
             FROM seller_input s
             LEFT JOIN actual_input a ON a.seller_input_id = s.id
             ORDER BY s.id"
        );
        return $stmt->fetchAll();
    }

    /**
     * Sinkronkan baris accrual dengan rate card Max Potential Earnings.
     *
     * Nilai quantity / price/unit / nilai accrual mengikuti Max Potential
     * selama belum diubah manual. Perubahan manual dikenali dengan membandingkan
     * nilai tersimpan terhadap snapshot `auto_*` yang terakhir ditulis server.
     */
    public static function syncFromMaxPotential(): void
    {
        $db = Database::getInstance();

        $rows = $db->query(
            'SELECT m.id, m.role,
                    m.quantity            AS mp_quantity,
                    m.price_per_unit      AS mp_price,
                    m.subtotal            AS mp_subtotal,
                    COALESCE(m.manual_amount, m.subtotal) AS mp_base,
                    s.id                  AS seller_id,
                    s.quantity            AS seller_quantity,
                    s.price_per_unit      AS seller_price,
                    s.seller_input        AS seller_value,
                    s.auto_quantity       AS seller_auto_quantity,
                    s.auto_price_per_unit AS seller_auto_price,
                    s.max_potential       AS seller_max_potential,
                    s.variance_reason     AS seller_reason,
                    s.notes               AS seller_notes
               FROM max_potential_earnings m
               LEFT JOIN seller_input s ON s.max_potential_id = m.id
              ORDER BY m.id'
        )->fetchAll();

        foreach ($rows as $row) {
            $autoQty   = round((float) $row['mp_quantity'], 2);
            $autoPrice = round((float) $row['mp_price'], 2);
            $autoValue = round((float) $row['mp_base'], 2);

            if ($row['seller_id'] !== null && self::isLocked((int) $row['seller_id'])) {
                continue;
            }

            $quantity = $autoQty;
            $price    = $autoPrice;
            $value    = $autoValue;
            $reason   = null;
            $notes    = null;

            if ($row['seller_id'] !== null) {
                $reason = $row['seller_reason'];
                $notes  = $row['seller_notes'];

                // Nilai dianggap manual hanya bila berbeda dari snapshot otomatis.
                if ($row['seller_quantity'] !== null && $row['seller_auto_quantity'] !== null
                    && abs((float) $row['seller_quantity'] - (float) $row['seller_auto_quantity']) > 0.004) {
                    $quantity = (float) $row['seller_quantity'];
                }
                if ($row['seller_price'] !== null && $row['seller_auto_price'] !== null
                    && abs((float) $row['seller_price'] - (float) $row['seller_auto_price']) > 0.004) {
                    $price = (float) $row['seller_price'];
                }
                if ($row['seller_value'] !== null && $row['seller_max_potential'] !== null
                    && abs((float) $row['seller_value'] - (float) $row['seller_max_potential']) > 0.004) {
                    $value = (float) $row['seller_value'];
                }
            }

            // Tanpa baseline (posisi di luar berkas periode ini) nilai accrual
            // dikosongkan supaya tidak menyisakan angka lama.
            if (abs($autoValue) < 0.005) {
                $value  = 0.0;
                $reason = null;
                $notes  = null;
            }

            self::upsert((int) $row['id'], $value, $reason, $notes, $quantity, $price, $autoQty, $autoPrice);
        }
    }

    /**
     * Kosongkan isian manual accrual: kembali ke nilai otomatis dari
     * Max Potential Earnings. Baris LOCKED tidak disentuh.
     */
    public static function resetManual(): void
    {
        $db = Database::getInstance();

        $db->exec(
            'UPDATE seller_input s
               JOIN max_potential_earnings m ON m.id = s.max_potential_id
               LEFT JOIN actual_input a ON a.seller_input_id = s.id
                SET s.quantity            = m.quantity,
                    s.price_per_unit      = m.price_per_unit,
                    s.auto_quantity       = m.quantity,
                    s.auto_price_per_unit = m.price_per_unit,
                    s.max_potential       = COALESCE(m.manual_amount, m.subtotal),
                    s.seller_input        = COALESCE(m.manual_amount, m.subtotal),
                    s.variance            = 0,
                    s.variance_pct        = 0,
                    s.variance_reason     = NULL,
                    s.notes               = NULL
              WHERE a.status IS NULL OR a.status <> ' . "'LOCKED'"
        );

        self::syncFromMaxPotential();
    }

    /**
     * Satu baris berdasarkan id.
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM seller_input WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Satu baris berdasarkan max_potential_id (relasi unik).
     */
    public static function findByMaxPotential(int $maxPotentialId): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM seller_input WHERE max_potential_id = ?'
        );
        $stmt->execute([$maxPotentialId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * True bila baris ini sudah dipakai actual_input yang berstatus LOCKED,
     * sehingga nilainya tidak boleh diubah lagi.
     */
    public static function isLocked(int $id): bool
    {
        $stmt = Database::getInstance()->prepare(
            "SELECT COUNT(*) FROM actual_input WHERE seller_input_id = ? AND status = 'LOCKED'"
        );
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Insert atau update berdasarkan max_potential_id.
     *
     * @throws \RuntimeException bila baris acuan tidak ada atau sudah LOCKED.
     */
    public static function upsert(
        int $maxPotentialId,
        ?float $sellerInput,
        ?string $varianceReason,
        ?string $notes,
        ?float $quantity = null,
        ?float $pricePerUnit = null,
        ?float $autoQuantity = null,
        ?float $autoPricePerUnit = null
    ): array {
        $maxPotential = MaxPotentialEarning::find($maxPotentialId);
        if ($maxPotential === null) {
            throw new \RuntimeException("max_potential_id {$maxPotentialId} tidak ditemukan.");
        }

        $existing = self::findByMaxPotential($maxPotentialId);
        if ($existing !== null && self::isLocked((int) $existing['id'])) {
            throw new \RuntimeException(
                'Baris "' . $maxPotential['role'] . '" sudah LOCKED dan tidak boleh diubah.'
            );
        }

        // Snapshot otomatis: bila tidak dikirim, pertahankan snapshot lama
        // (supaya edit manual tetap terdeteksi); baris baru memakai nilai
        // rate card sebagai acuan otomatis.
        $autoQty   = $autoQuantity;
        $autoPrice = $autoPricePerUnit;
        if ($existing !== null) {
            if ($autoQty === null && $existing['auto_quantity'] !== null) {
                $autoQty = (float) $existing['auto_quantity'];
            }
            if ($autoPrice === null && $existing['auto_price_per_unit'] !== null) {
                $autoPrice = (float) $existing['auto_price_per_unit'];
            }
        }
        if ($autoQty === null) {
            $autoQty = $maxPotential['quantity'] === null ? null : (float) $maxPotential['quantity'];
        }
        if ($autoPrice === null) {
            $autoPrice = $maxPotential['price_per_unit'] === null
                ? null
                : (float) $maxPotential['price_per_unit'];
        }

        // Nilai acuan: Max Potential manual bila diisi, kalau tidak hasil WFM.
        $base        = $maxPotential['base_amount'] !== null
            ? (float) $maxPotential['base_amount']
            : (float) $maxPotential['subtotal'];
        $variance    = $sellerInput === null ? null : round($sellerInput - $base, 2);
        $variancePct = ($sellerInput === null || $base === 0.0)
            ? null
            : round((($sellerInput - $base) / $base) * 100, 2);

        $stmt = Database::getInstance()->prepare(
            'INSERT INTO seller_input
                (max_potential_id, role, max_potential, quantity, price_per_unit,
                 auto_quantity, auto_price_per_unit, seller_input,
                 variance, variance_pct, variance_reason, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                role                  = VALUES(role),
                max_potential         = VALUES(max_potential),
                quantity              = VALUES(quantity),
                price_per_unit        = VALUES(price_per_unit),
                auto_quantity         = VALUES(auto_quantity),
                auto_price_per_unit   = VALUES(auto_price_per_unit),
                seller_input          = VALUES(seller_input),
                variance              = VALUES(variance),
                variance_pct          = VALUES(variance_pct),
                variance_reason       = VALUES(variance_reason),
                notes                 = VALUES(notes)'
        );
        $stmt->execute([
            $maxPotentialId,
            $maxPotential['role'],
            $base,
            $quantity,
            $pricePerUnit,
            $autoQty,
            $autoPrice,
            $sellerInput,
            $variance,
            $variancePct,
            $varianceReason,
            $notes,
        ]);

        $saved = self::findByMaxPotential($maxPotentialId);
        if ($saved === null) {
            throw new \RuntimeException('Gagal menyimpan seller input.');
        }
        return $saved;
    }
}

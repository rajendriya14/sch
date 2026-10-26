<?php

namespace Models;

use Config\Database;

/**
 * Actual Input — hasil verifikasi finance per baris seller input.
 *
 * variance dihitung terhadap `seller_input.seller_input`, dan `max_potential`
 * / `seller_input` disalin dari baris seller agar nilai acuan tidak bisa
 * dipalsukan client.
 *
 * Status LOCKED hanya bisa diset lewat endpoint approve, tidak lewat upsert.
 */
class ActualInput
{
    public const STATUS_DRAFT     = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_APPROVED  = 'APPROVED';
    public const STATUS_LOCKED    = 'LOCKED';

    /** Status yang boleh diminta client lewat POST (LOCKED bukan salah satunya). */
    public const WRITABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED,
    ];

    /**
     * Semua baris actual input.
     */
    public static function all(): array
    {
        $stmt = Database::getInstance()->query(
            'SELECT id, seller_input_id, role, max_potential, seller_input, quantity, price_per_unit,
                    actual_input, variance, variance_pct, variance_reason_finance, supporting_docs, notes, status
             FROM actual_input
             ORDER BY id'
        );
        return $stmt->fetchAll();
    }

    /**
     * Satu baris berdasarkan id.
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM actual_input WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Satu baris berdasarkan seller_input_id (relasi unik).
     */
    public static function findBySellerInput(int $sellerInputId): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM actual_input WHERE seller_input_id = ?'
        );
        $stmt->execute([$sellerInputId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Insert atau update berdasarkan seller_input_id.
     *
     * Baris yang sudah LOCKED dikembalikan apa adanya (tidak ditimpa).
     *
     * @throws \RuntimeException bila baris seller acuan tidak ada.
     */
    public static function upsert(
        int $sellerInputId,
        ?float $actualInput,
        ?string $varianceReasonFinance,
        ?string $supportingDocs,
        string $status = self::STATUS_DRAFT,
        ?float $quantity = null,
        ?string $notes = null,
        ?float $pricePerUnit = null
    ): array {
        $seller = SellerInput::find($sellerInputId);
        if ($seller === null) {
            throw new \RuntimeException("seller_input_id {$sellerInputId} tidak ditemukan.");
        }

        $existing = self::findBySellerInput($sellerInputId);
        if ($existing !== null && $existing['status'] === self::STATUS_LOCKED) {
            return $existing;
        }

        $maxPotential = $seller['max_potential'];
        $sellerValue  = $seller['seller_input'] === null ? null : (float) $seller['seller_input'];

        $variance = ($sellerValue === null || $actualInput === null)
            ? null
            : round($actualInput - $sellerValue, 2);

        $variancePct = ($sellerValue === null || $actualInput === null || $sellerValue === 0.0)
            ? null
            : round((($actualInput - $sellerValue) / $sellerValue) * 100, 2);

        $stmt = Database::getInstance()->prepare(
            'INSERT INTO actual_input
                (seller_input_id, role, max_potential, seller_input, quantity, price_per_unit, actual_input,
                 variance, variance_pct, variance_reason_finance, supporting_docs, notes, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                role                    = VALUES(role),
                max_potential           = VALUES(max_potential),
                seller_input            = VALUES(seller_input),
                quantity                = VALUES(quantity),
                price_per_unit          = VALUES(price_per_unit),
                actual_input            = VALUES(actual_input),
                variance                = VALUES(variance),
                variance_pct            = VALUES(variance_pct),
                variance_reason_finance = VALUES(variance_reason_finance),
                supporting_docs         = VALUES(supporting_docs),
                notes                   = VALUES(notes),
                status                  = VALUES(status)'
        );
        $stmt->execute([
            $sellerInputId,
            $seller['role'],
            $maxPotential,
            $sellerValue,
            $quantity,
            $pricePerUnit,
            $actualInput,
            $variance,
            $variancePct,
            $varianceReasonFinance,
            $supportingDocs,
            $notes,
            $status,
        ]);

        $saved = self::findBySellerInput($sellerInputId);
        if ($saved === null) {
            throw new \RuntimeException('Gagal menyimpan actual input.');
        }
        return $saved;
    }

    /**
     * Sinkronkan nilai acuan baris actual dengan baris accrual terbaru.
     *
     * role / max_potential / seller_input selalu mengikuti sumbernya; variance
     * dihitung ulang kecuali baris sudah LOCKED. Dipanggil setiap daftar
     * actual input dimuat supaya perubahan di Max Potential & Accrual terbawa.
     */
    public static function syncReferences(): void
    {
        $db = Database::getInstance();

        $db->exec(
            'UPDATE actual_input a
               JOIN seller_input s ON s.id = a.seller_input_id
                SET a.role          = s.role,
                    a.max_potential = s.max_potential,
                    a.seller_input  = s.seller_input,
                    a.variance      = CASE
                        WHEN a.actual_input IS NULL OR s.seller_input IS NULL THEN NULL
                        ELSE ROUND(a.actual_input - s.seller_input, 2) END,
                    a.variance_pct  = CASE
                        WHEN a.actual_input IS NULL OR s.seller_input IS NULL OR s.seller_input = 0 THEN NULL
                        ELSE ROUND((a.actual_input - s.seller_input) / s.seller_input * 100, 2) END
              WHERE a.status <> ' . "'LOCKED'"
        );
    }

    /**
     * Hapus isian manual finance (bukan LOCKED) sehingga baris kembali ke
     * nilai otomatis dari Accrual Input.
     */
    public static function resetManual(): void
    {
        $stmt = Database::getInstance()->prepare(
            'DELETE FROM actual_input WHERE status <> ?'
        );
        $stmt->execute([self::STATUS_LOCKED]);
    }

    /**
     * Set status baris menjadi APPROVED/LOCKED (idempotent).
     *
     * @throws \RuntimeException bila baris tidak ditemukan.
     */
    public static function setStatus(int $id, string $status = self::STATUS_LOCKED): array
    {
        if (!in_array($status, [self::STATUS_APPROVED, self::STATUS_LOCKED], true)) {
            throw new \RuntimeException("Status \"{$status}\" tidak boleh diset lewat endpoint approve.");
        }

        $existing = self::find($id);
        if ($existing === null) {
            throw new \RuntimeException("actual_input id {$id} tidak ditemukan.");
        }
        if ($existing['status'] === self::STATUS_LOCKED) {
            return $existing;
        }

        $stmt = Database::getInstance()->prepare('UPDATE actual_input SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);

        $updated = self::find($id);
        if ($updated === null) {
            throw new \RuntimeException('Gagal memperbarui status actual input.');
        }
        return $updated;
    }
}

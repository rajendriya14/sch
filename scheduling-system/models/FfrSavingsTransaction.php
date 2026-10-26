<?php

namespace Models;

use Config\Database;

/**
 * Buku transaksi "Tabunganku".
 *
 * Satu baris = satu kejadian pada satu aset:
 *   opening       saldo awal saat fitur ledger dipasang
 *   in            setor / tambah kepemilikan
 *   out           tarik / jual (uang keluar dari portofolio)
 *   transfer_out  keluar menuju aset lain
 *   transfer_in   masuk dari aset lain
 *   adjust        koreksi salah input (tidak dihitung sebagai arus dana)
 *
 * Nilai `quantity` selalu positif; arah ditentukan oleh `tx_type`.
 * `balance_after` menyimpan saldo aset setelah transaksi agar riwayat bisa
 * direkonsiliasi tanpa menghitung ulang seluruh buku.
 */
class FfrSavingsTransaction
{
    public const TYPES = [
        'opening'      => ['label' => 'Saldo Awal', 'icon' => 'bi-flag',        'class' => 'secondary'],
        'in'           => ['label' => 'Setor / Tambah', 'icon' => 'bi-arrow-down-circle', 'class' => 'success'],
        'out'          => ['label' => 'Tarik / Jual', 'icon' => 'bi-arrow-up-circle', 'class' => 'danger'],
        'transfer_out' => ['label' => 'Pindah Keluar', 'icon' => 'bi-arrow-left-right', 'class' => 'primary'],
        'transfer_in'  => ['label' => 'Pindah Masuk', 'icon' => 'bi-arrow-left-right', 'class' => 'info'],
        'adjust'       => ['label' => 'Koreksi', 'icon' => 'bi-wrench-adjustable', 'class' => 'warning'],
    ];

    /** Jenis transaksi yang boleh dikirim dari form. */
    public const INPUT_TYPES = ['in', 'out', 'transfer', 'adjust'];

    /** Alasan / tujuan transaksi. */
    public const CATEGORIES = [
        'rumah_tangga' => 'Kebutuhan rumah tangga',
        'pendidikan'   => 'Pendidikan',
        'kesehatan'    => 'Kesehatan',
        'darurat'      => 'Darurat',
        'aset_lain'    => 'Beli / pindah aset lain',
        'rebalance'    => 'Rebalance portofolio',
        'koreksi'      => 'Koreksi data',
        'lainnya'      => 'Lainnya',
    ];

    public static function typeMeta(?string $type): array
    {
        return self::TYPES[$type ?? ''] ?? ['label' => $type ?? '-', 'icon' => 'bi-dot', 'class' => 'secondary'];
    }

    public static function categoryLabel(?string $key): string
    {
        return self::CATEGORIES[$key ?? ''] ?? '—';
    }

    /** Arah keluar (mengurangi saldo aset). */
    public static function isOut(?string $type): bool
    {
        return in_array($type, ['out', 'transfer_out'], true);
    }

    /** Arah masuk (menambah saldo aset). */
    public static function isIn(?string $type): bool
    {
        return in_array($type, ['in', 'transfer_in'], true);
    }

    /** Simpan satu transaksi. */
    public static function create(array $row): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO ffr_savings_transactions
                (asset_id, tx_date, tx_type, quantity, price, gross_amount, fee, tax, amount,
                 cost_basis, realized_gain, balance_after, counterpart_asset_id, transfer_group,
                 category, note)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            (int) $row['asset_id'],
            (string) $row['tx_date'],
            (string) $row['tx_type'],
            (float) $row['quantity'],
            (float) $row['price'],
            (float) ($row['gross_amount'] ?? 0),
            (float) ($row['fee'] ?? 0),
            (float) ($row['tax'] ?? 0),
            (float) ($row['amount'] ?? 0),
            isset($row['cost_basis']) ? (float) $row['cost_basis'] : null,
            isset($row['realized_gain']) ? (float) $row['realized_gain'] : null,
            (float) ($row['balance_after'] ?? 0),
            isset($row['counterpart_asset_id']) ? (int) $row['counterpart_asset_id'] : null,
            $row['transfer_group'] ?? null,
            $row['category'] ?? null,
            $row['note'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Daftar transaksi dengan filter.
     *
     * @param array{from?:string,to?:string,asset_id?:int,type?:string,limit?:int} $filters
     */
    public static function all(array $filters = []): array
    {
        try {
            $db = Database::getInstance();
            $sql = 'SELECT t.*, a.name AS asset_name, a.type_key AS asset_type,
                           a.unit_label AS asset_unit, c.name AS counterpart_name
                    FROM ffr_savings_transactions t
                    LEFT JOIN ffr_savings_assets a ON a.id = t.asset_id
                    LEFT JOIN ffr_savings_assets c ON c.id = t.counterpart_asset_id
                    WHERE 1 = 1';
            $params = [];

            if (!empty($filters['from'])) {
                $sql .= ' AND t.tx_date >= ?';
                $params[] = $filters['from'];
            }
            if (!empty($filters['to'])) {
                $sql .= ' AND t.tx_date <= ?';
                $params[] = $filters['to'];
            }
            if (!empty($filters['asset_id'])) {
                $sql .= ' AND (t.asset_id = ? OR t.counterpart_asset_id = ?)';
                $params[] = (int) $filters['asset_id'];
                $params[] = (int) $filters['asset_id'];
            }
            if (!empty($filters['type'])) {
                $sql .= ' AND t.tx_type = ?';
                $params[] = $filters['type'];
            }

            $sql .= ' ORDER BY t.tx_date DESC, t.id DESC';
            if (!empty($filters['limit'])) {
                $sql .= ' LIMIT ' . (int) $filters['limit'];
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll() ?: [];
        } catch (\PDOException $e) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['quantity'] = (float) $row['quantity'];
            $row['price'] = (float) $row['price'];
            $row['gross_amount'] = (float) $row['gross_amount'];
            $row['fee'] = (float) $row['fee'];
            $row['tax'] = (float) $row['tax'];
            $row['amount'] = (float) $row['amount'];
            $row['balance_after'] = (float) $row['balance_after'];
            $row['realized_gain'] = $row['realized_gain'] !== null ? (float) $row['realized_gain'] : null;
            $row['cost_basis'] = $row['cost_basis'] !== null ? (float) $row['cost_basis'] : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * Ringkasan arus dana pada rentang tanggal.
     *
     * @return array{in:float,out:float,transfer:float,realized:float,count:int}
     */
    public static function summary(array $filters = []): array
    {
        $rows = self::all($filters);

        $summary = ['in' => 0.0, 'out' => 0.0, 'transfer' => 0.0, 'realized' => 0.0, 'count' => count($rows)];
        foreach ($rows as $row) {
            $type = $row['tx_type'];

            if ($type === 'in') {
                $summary['in'] += $row['amount'];
            } elseif ($type === 'out') {
                $summary['out'] += $row['amount'];
            } elseif ($type === 'transfer_out') {
                $summary['transfer'] += $row['amount'];
            } elseif ($type === 'opening') {
                // Saldo awal bukan arus dana — hanya titik mulai pencatatan.
                continue;
            }

            if ($row['realized_gain'] !== null) {
                $summary['realized'] += $row['realized_gain'];
            }
        }

        return $summary;
    }

    /** Jumlah transaksi untuk satu aset. */
    public static function countForAsset(int $assetId): int
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT COUNT(*) FROM ffr_savings_transactions WHERE asset_id = ?'
            );
            $stmt->execute([$assetId]);
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            return 0;
        }
    }

    /**
     * Pastikan setiap aset punya saldo awal.
     *
     * Aset yang sudah ada sebelum fitur ledger dipasang belum punya riwayat,
     * jadi dibuatkan satu transaksi "Saldo Awal" sebesar kepemilikan saat ini.
     */
    public static function ensureOpeningBalances(): void
    {
        try {
            $db = Database::getInstance();
            $rows = $db->query(
                'SELECT a.* FROM ffr_savings_assets a
                 WHERE NOT EXISTS (
                     SELECT 1 FROM ffr_savings_transactions t WHERE t.asset_id = a.id
                 )'
            )->fetchAll() ?: [];
        } catch (\PDOException $e) {
            return;
        }

        foreach ($rows as $asset) {
            $quantity = (float) $asset['quantity'];
            if ($quantity <= 0) {
                continue;
            }

            $isCash = FfrSavingsAsset::isCash($asset['type_key'] ?? '');
            $price = $isCash ? 1.0 : (float) $asset['avg_price'];
            $mode = FfrSavingsAsset::valuationMode($asset['type_key'] ?? '');
            $divisor = $mode === 'percent' ? 100.0 : 1.0;
            $rate = $isCash ? 1.0 : max(0.0001, (float) ($asset['exchange_rate'] ?? 1));
            $amount = $quantity * $price / $divisor * $rate;

            self::create([
                'asset_id'     => (int) $asset['id'],
                'tx_date'      => !empty($asset['buy_date'])
                    ? (string) $asset['buy_date']
                    : (!empty($asset['created_at']) ? substr((string) $asset['created_at'], 0, 10) : date('Y-m-d')),
                'tx_type'      => 'opening',
                'quantity'     => $quantity,
                'price'        => $price,
                'gross_amount' => $amount,
                'amount'       => $amount,
                'balance_after' => $quantity,
                'category'     => null,
                'note'         => 'Saldo awal saat buku transaksi diaktifkan',
            ]);
        }
    }

    /** Transaksi keluar pada rentang tanggal (untuk penanda grafik). */
    public static function flowEvents(string $from, string $to): array
    {
        $rows = self::all(['from' => $from, 'to' => $to]);
        $events = [];

        foreach ($rows as $row) {
            if ($row['tx_type'] === 'in') {
                $events[] = [
                    'date'      => $row['tx_date'],
                    'direction' => 'in',
                    'amount'    => $row['amount'],
                    'label'     => $row['asset_name'] ?? '-',
                ];
            } elseif ($row['tx_type'] === 'out') {
                $events[] = [
                    'date'      => $row['tx_date'],
                    'direction' => 'out',
                    'amount'    => $row['amount'],
                    'label'     => $row['asset_name'] ?? '-',
                ];
            }
        }

        return $events;
    }
}

<?php

namespace Models;

use Config\Database;

/**
 * Satu baris = satu kepemilikan aset investasi (Tabunganku).
 *
 * Nilai tidak disimpan: modal = jumlah × harga beli (+ biaya beli), nilai
 * kini = perkiraan hasil jual memakai harga jual/buyback (− biaya jual &
 * pajak). Kurs ke Rupiah dipakai bila harga dalam mata uang asing.
 * Aset cash (dana cash / deposito) memakai harga 1 sehingga nilai = saldo,
 * sedangkan obligasi dinilai dalam persen nominal.
 */
class FfrSavingsAsset
{
    /** Jenis investasi yang didukung + label, ikon, dan cara menilai. */
    public const TYPES = [
        'tabungan'  => ['label' => 'Dana Cash',        'icon' => 'bi-cash-coin',         'class' => 'success',  'mode' => 'cash',    'unit' => 'Rp'],
        'deposito'  => ['label' => 'Deposito',         'icon' => 'bi-bank',              'class' => 'secondary','mode' => 'cash',    'unit' => 'Rp'],
        'emas'      => ['label' => 'Emas / Logam',     'icon' => 'bi-gem',               'class' => 'warning',  'mode' => 'unit',    'unit' => 'gram'],
        'valas'     => ['label' => 'Mata Uang Asing',  'icon' => 'bi-currency-dollar',   'class' => 'primary',  'mode' => 'unit',    'unit' => 'USD'],
        'saham'     => ['label' => 'Saham',            'icon' => 'bi-graph-up-arrow',    'class' => 'danger',   'mode' => 'unit',    'unit' => 'lembar'],
        'obligasi'  => ['label' => 'Obligasi / SBN',   'icon' => 'bi-file-earmark-text', 'class' => 'info',     'mode' => 'percent', 'unit' => 'nominal'],
        'reksadana' => ['label' => 'Reksa Dana',       'icon' => 'bi-pie-chart',         'class' => 'purple',   'mode' => 'unit',    'unit' => 'unit'],
        'kripto'    => ['label' => 'Kripto',           'icon' => 'bi-currency-bitcoin',  'class' => 'dark',     'mode' => 'unit',    'unit' => 'koin'],
        'properti'  => ['label' => 'Properti',         'icon' => 'bi-house-door',        'class' => 'teal',     'mode' => 'unit',    'unit' => 'unit'],
        'lainnya'   => ['label' => 'Lainnya',          'icon' => 'bi-three-dots',        'class' => 'secondary','mode' => 'unit',    'unit' => 'unit'],
    ];

    /** Jenis yang nilainya berupa saldo Rupiah (harga per unit = 1). */
    public const CASH_TYPES = ['tabungan', 'deposito'];

    /** Satuan yang sering dipakai (masih bisa diketik bebas). */
    public const UNITS = ['Rp', 'gram', 'lembar', 'lot', 'unit', 'USD', 'EUR', 'SGD', 'koin', 'nominal'];

    /** Mata uang yang tersedia di form valas. */
    public const CURRENCIES = ['IDR', 'USD', 'EUR', 'SGD', 'JPY', 'GBP', 'AUD', 'CNY', 'HKD', 'MYR', 'SAR'];

    /**
     * Institusi yang sering dipakai, dipakai sebagai saran (datalist).
     * Tetap bisa diketik bebas sesuai kebutuhan.
     */
    public const INSTITUTIONS = [
        'tabungan' => [
            'BCA', 'Bank Mandiri', 'BNI', 'BRI', 'BSI', 'BTN', 'CIMB Niaga', 'Permata',
            'Danamon', 'OCBC', 'Maybank', 'Panin', 'Bank Jago', 'SeaBank', 'Bank Neo',
            'Jenius (BTPN)', 'Blu (BCA Digital)', 'Bank DKI', 'Bank BJB', 'Tunai / Cash',
            'OVO', 'GoPay', 'DANA', 'ShopeePay', 'Lainnya',
        ],
        'deposito' => [
            'BCA', 'Bank Mandiri', 'BNI', 'BRI', 'BSI', 'BTN', 'CIMB Niaga', 'Permata',
            'Danamon', 'OCBC', 'Maybank', 'Panin', 'Bank Jago', 'SeaBank', 'Lainnya',
        ],
        'emas' => [
            'Antam', 'UBS', 'Galeri24', 'Pegadaian', 'Toko Emas', 'Perhiasan', 'Lainnya',
        ],
        'valas' => [
            'Bank BCA', 'Bank Mandiri', 'Bank BNI', 'Bank BRI', 'Bank BSI',
            'Money Changer', 'Wise', 'PayPal', 'Lainnya',
        ],
        'saham' => [
            'Mirae Asset', 'Stockbit Sekuritas', 'Ajaib', 'Bibit', 'IPOT', 'BNI Sekuritas',
            'Mandiri Sekuritas', 'Lainnya',
        ],
        'obligasi' => [
            'Kemenkeu (SBN)', 'Bibit', 'Bareksa', 'Ajaib', 'Bank', 'Lainnya',
        ],
        'reksadana' => [
            'Bibit', 'Bareksa', 'Ajaib', 'Bank', 'Manajer Investasi', 'Lainnya',
        ],
        'kripto' => [
            'Indodax', 'Tokocrypto', 'Pintu', 'Reku', 'Binance', 'Lainnya',
        ],
        'properti' => ['Developer', 'Agen Properti', 'Lainnya'],
        'lainnya' => [],
    ];

    public static function all(bool $activeOnly = false): array
    {
        $db = Database::getInstance();
        $sql = 'SELECT * FROM ffr_savings_assets';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        try {
            return $db->query($sql)->fetchAll() ?: [];
        } catch (\PDOException $e) {
            return [];
        }
    }

    public static function find(int $id): ?array
    {
        try {
            $stmt = Database::getInstance()->prepare('SELECT * FROM ffr_savings_assets WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO ffr_savings_assets
             (type_key, name, ticker, institution, account_no, gold_brand, owner, unit_label,
              quantity, gold_weight_gram, avg_price, current_price, sell_price, fee_buy, fee_sell,
              tax_sell, coupon_rate, maturity_date, currency, exchange_rate, buy_date, price_date,
              price_source, price_fetched_at, notes, is_active, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute(self::bindings($data));
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE ffr_savings_assets
             SET type_key = ?, name = ?, ticker = ?, institution = ?, account_no = ?, gold_brand = ?,
                 owner = ?, unit_label = ?, quantity = ?, gold_weight_gram = ?, avg_price = ?,
                 current_price = ?, sell_price = ?, fee_buy = ?, fee_sell = ?, tax_sell = ?,
                 coupon_rate = ?, maturity_date = ?, currency = ?, exchange_rate = ?, buy_date = ?,
                 price_date = ?, price_source = ?, price_fetched_at = ?, notes = ?, is_active = ?,
                 sort_order = ?
             WHERE id = ?'
        );
        $params = self::bindings($data);
        $params[] = $id;
        return $stmt->execute($params);
    }

    /** Perbarui harga sekarang saja (dipakai pembaruan cepat dari tabel). */
    public static function updatePrice(int $id, float $price): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE ffr_savings_assets SET current_price = ? WHERE id = ?'
        );
        return $stmt->execute([$price, $id]);
    }

    /**
     * Perbarui harga beli & harga jual/buyback sekaligus dari tabel.
     */
    public static function updatePrices(int $id, float $currentPrice, ?float $sellPrice = null): bool
    {
        if ($sellPrice === null) {
            return self::updatePrice($id, $currentPrice);
        }

        $stmt = Database::getInstance()->prepare(
            'UPDATE ffr_savings_assets SET current_price = ?, sell_price = ? WHERE id = ?'
        );
        return $stmt->execute([$currentPrice, $sellPrice, $id]);
    }

    /** Perbarui saldo aset cash (dana cash / deposito) dari tabel. */
    public static function updateQuantity(int $id, float $quantity): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE ffr_savings_assets SET quantity = ? WHERE id = ?'
        );
        return $stmt->execute([$quantity, $id]);
    }

    /** Catat tanggal harga terakhir diperbarui. */
    public static function updatePriceDate(int $id, string $date): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE ffr_savings_assets SET price_date = ? WHERE id = ?'
        );
        return $stmt->execute([$date, $id]);
    }

    /**
     * Simpan hasil pembaruan harga emas (massal) untuk satu aset.
     * Harga disimpan per satuan (per gram), bukan total per pecahan.
     */
    public static function updateGoldValuation(
        int $id,
        float $buyPerGram,
        float $buybackPerGram,
        string $priceDate,
        string $source,
        string $fetchedAt,
        string $brand,
        ?float $weightGram = null
    ): bool {
        $stmt = Database::getInstance()->prepare(
            'UPDATE ffr_savings_assets
             SET current_price = ?, sell_price = ?, price_date = ?, price_source = ?,
                 price_fetched_at = ?, gold_brand = ?,
                 gold_weight_gram = COALESCE(gold_weight_gram, ?)
             WHERE id = ?'
        );

        return $stmt->execute([
            $buyPerGram,
            $buybackPerGram,
            $priceDate,
            $source,
            $fetchedAt,
            self::isBrandKey($brand) ? $brand : null,
            $weightGram,
            $id,
        ]);
    }

    private static function isBrandKey(string $brand): bool
    {
        return isset(\Models\FfrGoldPrice::BRANDS[$brand]);
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::getInstance()->prepare('DELETE FROM ffr_savings_assets WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Jumlah aset per jenis, dipakai halaman Master Kategori.
     *
     * @return array<string,int> type_key => jumlah aset
     */
    public static function countsByType(): array
    {
        $counts = [];

        try {
            $stmt = Database::getInstance()->query(
                'SELECT type_key, COUNT(*) AS total FROM ffr_savings_assets GROUP BY type_key'
            );
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $counts[(string) $row['type_key']] = (int) $row['total'];
            }
        } catch (\PDOException $e) {
            return [];
        }

        return $counts;
    }

    /** Perbarui saldo aset (satuan aset, bukan Rupiah). */
    public static function setBalance(int $id, float $quantity, ?float $avgPrice = null): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE ffr_savings_assets
             SET quantity = ?, avg_price = COALESCE(?, avg_price)
             WHERE id = ?'
        );
        return $stmt->execute([$quantity, $avgPrice, $id]);
    }

    /**
     * Arsipkan aset: hilang dari portofolio tetapi riwayat transaksinya tetap.
     */
    public static function archive(int $id): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE ffr_savings_assets SET is_active = 0, archived_at = ? WHERE id = ?'
        );
        return $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    /** Aktifkan kembali aset yang diarsipkan. */
    public static function unarchive(int $id): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE ffr_savings_assets SET is_active = 1, archived_at = NULL WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /**
     * Nilai satu satuan aset dalam Rupiah.
     * cash → 1 ; obligasi → harga% / 100 ; lainnya → harga × kurs.
     */
    public static function unitValue(array $asset, float $price): float
    {
        if (self::isCash($asset['type_key'] ?? '')) {
            return 1.0;
        }

        $mode = self::valuationMode($asset['type_key'] ?? '');
        $divisor = $mode === 'percent' ? 100.0 : 1.0;
        $rate = (float) ($asset['exchange_rate'] ?? 1);
        if ($rate <= 0) {
            $rate = 1.0;
        }

        return $price / $divisor * $rate;
    }

    /** Aset aktif untuk pemilih di form transaksi. */
    public static function activeList(): array
    {
        return array_values(array_filter(
            self::all(true),
            fn($a) => (float) ($a['quantity'] ?? 0) > 0
        ));
    }

    /** Jenis yang tidak dikenal tetap ditampilkan dengan label "Lainnya". */
    public static function typeMeta(?string $key): array
    {
        return self::TYPES[$key ?? ''] ?? self::TYPES['lainnya'];
    }

    /** Apakah jenis ini berupa saldo Rupiah (dana cash / deposito)? */
    public static function isCash(?string $key): bool
    {
        return in_array($key ?? '', self::CASH_TYPES, true);
    }

    /**
     * Cara menghitung nilai:
     *   cash    → nilai = jumlah (saldo Rupiah)
     *   percent → nilai = jumlah × harga% / 100 (obligasi)
     *   unit    → nilai = jumlah × harga
     */
    public static function valuationMode(?string $key): string
    {
        return self::typeMeta($key)['mode'] ?? 'unit';
    }

    /** Saran institusi untuk jenis tertentu. */
    public static function institutionSuggestions(?string $key): array
    {
        return self::INSTITUTIONS[$key ?? ''] ?? [];
    }

    private static function bindings(array $data): array
    {
        $type = isset(self::TYPES[$data['type_key'] ?? '']) ? (string) $data['type_key'] : 'lainnya';
        $isCash = self::isCash($type);
        $isGold = $type === 'emas';

        $avgPrice = $isCash ? 1.0 : (float) ($data['avg_price'] ?? 0);
        $currentPrice = $isCash ? 1.0 : (float) ($data['current_price'] ?? 0);
        $sellPrice = $isCash ? 0.0 : (float) ($data['sell_price'] ?? 0);

        $goldBrand = $isGold && isset(FfrGoldPrice::BRANDS[$data['gold_brand'] ?? ''])
            ? (string) $data['gold_brand']
            : null;
        $goldWeight = $isGold && (float) ($data['gold_weight_gram'] ?? 0) > 0
            ? (float) $data['gold_weight_gram']
            : null;

        return [
            $type,
            trim((string) ($data['name'] ?? '')),
            trim((string) ($data['ticker'] ?? '')) ?: null,
            trim((string) ($data['institution'] ?? '')) ?: null,
            trim((string) ($data['account_no'] ?? '')) ?: null,
            $goldBrand,
            in_array($data['owner'] ?? '', ['Ivan', 'Ricka', 'Bersama'], true) ? $data['owner'] : 'Bersama',
            $isCash ? 'Rp' : (trim((string) ($data['unit_label'] ?? 'unit')) ?: 'unit'),
            (float) ($data['quantity'] ?? 0),
            $goldWeight,
            $avgPrice,
            $currentPrice,
            $sellPrice,
            max(0.0, (float) ($data['fee_buy'] ?? 0)),
            max(0.0, (float) ($data['fee_sell'] ?? 0)),
            max(0.0, (float) ($data['tax_sell'] ?? 0)),
            max(0.0, (float) ($data['coupon_rate'] ?? 0)),
            self::nullableDate($data['maturity_date'] ?? null),
            $isCash ? 'IDR' : (strtoupper(trim((string) ($data['currency'] ?? 'IDR'))) ?: 'IDR'),
            $isCash ? 1.0 : ((float) ($data['exchange_rate'] ?? 1) ?: 1),
            self::nullableDate($data['buy_date'] ?? null),
            self::nullableDate($data['price_date'] ?? null),
            $isGold ? (trim((string) ($data['price_source'] ?? '')) ?: null) : null,
            $isGold ? self::nullableDateTime($data['price_fetched_at'] ?? null) : null,
            trim((string) ($data['notes'] ?? '')) ?: null,
            !empty($data['is_active']) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
        ];
    }

    /** Tanggal kosong disimpan NULL, format selain YYYY-MM-DD juga NULL. */
    private static function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    /** Datetime kosong disimpan NULL, format selain YYYY-MM-DD HH:MM:SS juga NULL. */
    private static function nullableDateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) ? $value : null;
    }
}

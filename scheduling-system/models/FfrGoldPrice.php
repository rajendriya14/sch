<?php

namespace Models;

use Config\Database;

/**
 * Harga emas harian per brand & pecahan.
 *
 * Satu baris = satu harga (beli/buyback) untuk satu brand pada satu tanggal
 * dan satu pecahan. Harga bisa berasal dari sumber otomatis
 * (mis. harga-emas.org) atau diisi manual; kolom `source` dan `is_manual`
 * menyimpan jejak asalnya agar angka tidak pernah tampil tanpa keterangan.
 */
class FfrGoldPrice
{
    /** Brand yang didukung + label & warnanya. */
    public const BRANDS = [
        'antam'     => ['label' => 'Antam',             'class' => 'warning'],
        'ubs'       => ['label' => 'UBS',               'class' => 'primary'],
        'semar'     => ['label' => 'Semar Nusantara',   'class' => 'danger'],
        'pegadaian' => ['label' => 'Pegadaian',         'class' => 'success'],
        'galeri24'  => ['label' => 'Galeri24',          'class' => 'info'],
        'lotus'     => ['label' => 'Lotus Archipelago', 'class' => 'purple'],
        'lainnya'   => ['label' => 'Lainnya',           'class' => 'secondary'],
    ];

    /** Pecahan yang dipakai di form & perbandingan. */
    public const WEIGHTS = [0.5, 1, 2, 3, 5, 10, 25, 50, 100];

    /** Brand yang biasanya punya harga otomatis dari provider. */
    public const AUTO_BRANDS = ['antam', 'ubs', 'pegadaian'];

    public static function brandMeta(?string $key): array
    {
        return self::BRANDS[$key ?? ''] ?? self::BRANDS['lainnya'];
    }

    public static function isBrand(?string $key): bool
    {
        return isset(self::BRANDS[$key ?? '']);
    }

    /** Kunci pecahan yang konsisten: 1, 0.5, 2.5 */
    public static function weightKey(float $weight): string
    {
        $text = rtrim(rtrim(number_format($weight, 3, '.', ''), '0'), '.');
        return $text === '' ? '0' : $text;
    }

    /** Pecahan terdekat dari daftar WEIGHTS (untuk lookup harga). */
    public static function nearestWeight(float $weight): float
    {
        $best = 1.0;
        $bestDiff = PHP_FLOAT_MAX;
        foreach (self::WEIGHTS as $candidate) {
            $diff = abs($candidate - $weight);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = (float) $candidate;
            }
        }
        return $best;
    }

    /** Apakah pecahan ini termasuk daftar yang didukung (toleran pembulatan)? */
    public static function isSupportedWeight(float $weight): bool
    {
        foreach (self::WEIGHTS as $candidate) {
            if (abs((float) $candidate - $weight) < 0.0005) {
                return true;
            }
        }
        return false;
    }

    /** Semua harga pada satu tanggal: brand => weightKey => row. */
    public static function forDate(string $date): array
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT * FROM ffr_gold_prices WHERE price_date = ? ORDER BY brand_key ASC, weight_gram ASC'
            );
            $stmt->execute([$date]);
            $rows = $stmt->fetchAll() ?: [];
        } catch (\PDOException $e) {
            return [];
        }

        return self::group($rows);
    }

    /**
     * Harga terbaru per brand (tanggal terakhir yang tersedia).
     *
     * @return array brand => weightKey => row
     */
    public static function latestByBrand(): array
    {
        try {
            $stmt = Database::getInstance()->query(
                'SELECT p.* FROM ffr_gold_prices p
                 JOIN (
                     SELECT brand_key, MAX(price_date) AS max_date
                     FROM ffr_gold_prices
                     GROUP BY brand_key
                 ) m ON m.brand_key = p.brand_key AND m.max_date = p.price_date
                 ORDER BY p.brand_key ASC, p.weight_gram ASC'
            );
            $rows = $stmt->fetchAll() ?: [];
        } catch (\PDOException $e) {
            return [];
        }

        return self::group($rows);
    }

    /** Satu harga paling baru untuk brand + pecahan. */
    public static function latestFor(string $brand, float $weight): ?array
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT * FROM ffr_gold_prices
                 WHERE brand_key = ? AND weight_gram = ?
                 ORDER BY price_date DESC LIMIT 1'
            );
            $stmt->execute([$brand, $weight]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }

            // Pecahan yang diminta belum ada → pakai pecahan lain terbaru
            // dari brand yang sama (perhitungan tetap per gram).
            $stmt = Database::getInstance()->prepare(
                'SELECT * FROM ffr_gold_prices
                 WHERE brand_key = ?
                 ORDER BY price_date DESC, weight_gram ASC LIMIT 1'
            );
            $stmt->execute([$brand]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /** Waktu ambil otomatis terakhir (null bila belum pernah). */
    public static function lastFetchedAt(): ?string
    {
        try {
            $value = Database::getInstance()
                ->query("SELECT MAX(fetched_at) FROM ffr_gold_prices WHERE is_manual = 0")
                ->fetchColumn();
            return $value ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /** Simpan/perbarui satu harga. */
    public static function upsert(array $row): bool
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'INSERT INTO ffr_gold_prices
                    (price_date, brand_key, weight_gram, buy_price, buyback_price,
                     spot_idr_gram, spot_usd_oz, usd_idr, source, source_url, is_manual, fetched_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    buy_price = VALUES(buy_price),
                    buyback_price = VALUES(buyback_price),
                    spot_idr_gram = VALUES(spot_idr_gram),
                    spot_usd_oz = VALUES(spot_usd_oz),
                    usd_idr = VALUES(usd_idr),
                    source = VALUES(source),
                    source_url = VALUES(source_url),
                    is_manual = VALUES(is_manual),
                    fetched_at = VALUES(fetched_at)'
            );

            return $stmt->execute([
                $row['price_date'],
                $row['brand_key'],
                (float) $row['weight_gram'],
                (float) $row['buy_price'],
                $row['buyback_price'] !== null ? (float) $row['buyback_price'] : null,
                $row['spot_idr_gram'] !== null ? (float) $row['spot_idr_gram'] : null,
                $row['spot_usd_oz'] !== null ? (float) $row['spot_usd_oz'] : null,
                $row['usd_idr'] !== null ? (float) $row['usd_idr'] : null,
                (string) ($row['source'] ?? 'manual'),
                $row['source_url'] ?? null,
                !empty($row['is_manual']) ? 1 : 0,
                $row['fetched_at'] ?? null,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /** Hapus isian manual pada tanggal tertentu (kembali ke harga otomatis). */
    public static function deleteManual(string $date, string $brand, float $weight): bool
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'DELETE FROM ffr_gold_prices
                 WHERE price_date = ? AND brand_key = ? AND weight_gram = ? AND is_manual = 1'
            );
            return $stmt->execute([$date, $brand, $weight]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /** Riwayat beberapa hari terakhir (untuk tabel histori). */
    public static function history(int $days = 14, ?float $weight = null): array
    {
        try {
            $sql = 'SELECT * FROM ffr_gold_prices
                    WHERE price_date >= DATE_SUB(CURDATE(), INTERVAL ' . max(1, $days) . ' DAY)';
            if ($weight !== null) {
                $sql .= ' AND weight_gram = ' . (float) $weight;
            }
            $sql .= ' ORDER BY price_date DESC, weight_gram ASC, brand_key ASC';

            return Database::getInstance()->query($sql)->fetchAll() ?: [];
        } catch (\PDOException $e) {
            return [];
        }
    }

    /** Kelompokkan baris menjadi brand => weightKey => row. */
    private static function group(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $row['buy_price'] = (float) $row['buy_price'];
            $row['buyback_price'] = $row['buyback_price'] !== null ? (float) $row['buyback_price'] : null;
            $row['weight_gram'] = (float) $row['weight_gram'];
            $row['spot_idr_gram'] = $row['spot_idr_gram'] !== null ? (float) $row['spot_idr_gram'] : null;
            $row['spot_usd_oz'] = $row['spot_usd_oz'] !== null ? (float) $row['spot_usd_oz'] : null;
            $row['usd_idr'] = $row['usd_idr'] !== null ? (float) $row['usd_idr'] : null;
            $row['is_manual'] = (int) $row['is_manual'];

            $brand = (string) $row['brand_key'];
            $grouped[$brand][self::weightKey((float) $row['weight_gram'])] = $row;
        }
        return $grouped;
    }
}

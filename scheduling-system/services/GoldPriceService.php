<?php

namespace Services;

use Models\FfrGoldPrice;
use Models\FfrSavingsAsset;

/**
 * Logika harga emas "Tabunganku".
 *
 * Tiga tanggung jawab:
 *   1. Menyegarkan harga otomatis dari provider (dengan cache 15 menit).
 *   2. Menyusun perbandingan antar brand (harga per gram, spread, premium
 *      terhadap spot) beserta penanda netral: termurah & spread terkecil.
 *   3. Menyiapkan pratinjau pembaruan massal aset emas sebelum disimpan.
 *
 * Semua angka Rupiah. Bila sumber otomatis gagal, sistem memakai harga
 * manual/terakhir dan menandainya sebagai data lama.
 */
class GoldPriceService
{
    /** Jangan ambil ulang dari provider dalam rentang ini (menit). */
    private const STALE_MINUTES = 15;

    /** @var GoldPriceProvider[] */
    private array $providers;

    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?: [new GoldPriceProviders\HargaEmasOrgProvider()];
    }

    public function providerLabels(): array
    {
        return array_map(fn(GoldPriceProvider $p) => $p->label(), $this->providers);
    }

    /**
     * Ambil harga dari provider bila cache sudah kedaluwarsa.
     *
     * @return array{fetched:bool, reason?:string, stored?:int, skipped?:int,
     *               source?:string, fetched_at?:string, spot?:?array}
     */
    public function ensureFresh(bool $force = false): array
    {
        $last = FfrGoldPrice::lastFetchedAt();
        $todayRows = FfrGoldPrice::forDate(date('Y-m-d'));

        if (!$force && $last && strtotime($last) > time() - (self::STALE_MINUTES * 60)) {
            return ['fetched' => false, 'reason' => 'cache', 'fetched_at' => $last];
        }

        foreach ($this->providers as $provider) {
            $payload = $provider->fetch();
            if (!$payload || empty($payload['brands'])) {
                continue;
            }

            $stored = 0;
            $skipped = 0;

            foreach ($payload['brands'] as $brand => $data) {
                if (!FfrGoldPrice::isBrand($brand) || empty($data['weights'])) {
                    continue;
                }

                foreach ($data['weights'] as $weight => $price) {
                    $weightKey = FfrGoldPrice::weightKey((float) $weight);
                    $existing = $todayRows[$brand][$weightKey] ?? null;

                    // Isian manual pengguna tidak boleh tertimpa otomatis
                    // (termasuk saat tombol "Ambil Semua" ditekan).
                    if ($existing && !empty($existing['is_manual'])) {
                        $skipped++;
                        continue;
                    }

                    $spot = $data['spot'] ?? $payload['spot'] ?? null;

                    $ok = FfrGoldPrice::upsert([
                        'price_date'    => date('Y-m-d'),
                        'brand_key'     => $brand,
                        'weight_gram'   => (float) $weight,
                        'buy_price'     => (float) $price['buy'],
                        'buyback_price' => $price['buyback'] ?? null,
                        'spot_idr_gram' => $spot['idr_gram'] ?? null,
                        'spot_usd_oz'   => $spot['usd_oz'] ?? null,
                        'usd_idr'       => $spot['usd_idr'] ?? null,
                        'source'        => $payload['source'] ?? $provider->key(),
                        'source_url'    => $payload['source_url'] ?? null,
                        'is_manual'     => 0,
                        'fetched_at'    => $payload['fetched_at'] ?? date('Y-m-d H:i:s'),
                    ]);

                    if ($ok) {
                        $stored++;
                    }
                }
            }

            if ($stored > 0) {
                return [
                    'fetched'    => true,
                    'stored'     => $stored,
                    'skipped'    => $skipped,
                    'source'     => $payload['source'] ?? $provider->key(),
                    'fetched_at' => $payload['fetched_at'] ?? date('Y-m-d H:i:s'),
                    'spot'       => $payload['spot'] ?? null,
                ];
            }
        }

        return ['fetched' => false, 'reason' => 'provider'];
    }

    /**
     * Perbandingan harga semua brand untuk satu pecahan.
     *
     * @return array{weight:float, rows:array, spot:?array, fetched_at:?string,
     *               date:?string, stale:bool, auto_brands:array, manual_only:array}
     */
    public function comparison(float $weight): array
    {
        $weight = FfrGoldPrice::isSupportedWeight($weight)
            ? $weight
            : FfrGoldPrice::nearestWeight($weight);
        $weightKey = FfrGoldPrice::weightKey($weight);

        $today = date('Y-m-d');
        $todayRows = FfrGoldPrice::forDate($today);
        $latestRows = FfrGoldPrice::latestByBrand();

        $rows = [];
        $spot = null;
        $fetchedAt = null;
        $date = null;

        foreach (FfrGoldPrice::BRANDS as $brand => $meta) {
            $row = $this->pickRow($todayRows[$brand] ?? [], $weightKey, $weight)
                ?? $this->pickRow($latestRows[$brand] ?? [], $weightKey, $weight);

            if (!$row) {
                continue;
            }

            $rowWeight = (float) $row['weight_gram'] > 0 ? (float) $row['weight_gram'] : 1.0;
            $buy = (float) $row['buy_price'];
            if ($buy <= 0) {
                continue;
            }
            $buyback = $row['buyback_price'] !== null ? (float) $row['buyback_price'] : null;

            $buyPerGram = $buy / $rowWeight;
            $buybackPerGram = $buyback !== null ? $buyback / $rowWeight : null;

            if ($spot === null && !empty($row['spot_idr_gram'])) {
                $spot = [
                    'idr_gram' => (float) $row['spot_idr_gram'],
                    'usd_oz'   => $row['spot_usd_oz'] !== null ? (float) $row['spot_usd_oz'] : null,
                    'usd_idr'  => $row['usd_idr'] !== null ? (float) $row['usd_idr'] : null,
                ];
            }
            if ($fetchedAt === null && !empty($row['fetched_at'])) {
                $fetchedAt = $row['fetched_at'];
            }
            $date = $date === null ? $row['price_date'] : max($date, $row['price_date']);

            $spread = null;
            if ($buybackPerGram !== null && $buyPerGram > 0) {
                $spread = round((($buybackPerGram - $buyPerGram) / $buyPerGram) * 100, 2);
            }

            $rows[$brand] = [
                'brand'           => $brand,
                'label'           => $meta['label'],
                'class'           => $meta['class'],
                'weight'          => $rowWeight,
                'requested_weight' => $weight,
                'buy'             => $buy,
                'buyback'         => $buyback,
                'buy_per_gram'    => round($buyPerGram, 2),
                'buyback_per_gram' => $buybackPerGram !== null ? round($buybackPerGram, 2) : null,
                'spread'          => $spread,
                'premium'         => ($spot && $spot['idr_gram'] > 0)
                    ? round((($buyPerGram - $spot['idr_gram']) / $spot['idr_gram']) * 100, 2)
                    : null,
                'price_date'      => $row['price_date'],
                'fetched_at'      => $row['fetched_at'],
                'source'          => $row['source'],
                'is_manual'       => !empty($row['is_manual']),
                'stale'           => $row['price_date'] < $today,
                'tags'            => [],
            ];
        }

        // Penanda netral: termurah, buyback terbaik, spread tersempit.
        $withBuyback = array_filter($rows, fn($r) => $r['buyback_per_gram'] !== null && $r['spread'] !== null && $r['spread'] < 0);
        $withBuy = array_filter($rows, fn($r) => $r['buy_per_gram'] > 0);

        if ($withBuy) {
            $cheapest = null;
            foreach ($withBuy as $brand => $row) {
                if ($cheapest === null || $row['buy_per_gram'] < $withBuy[$cheapest]['buy_per_gram']) {
                    $cheapest = $brand;
                }
            }
            if ($cheapest !== null) {
                $rows[$cheapest]['tags'][] = 'termurah';
            }
        }

        if ($withBuyback) {
            $bestBuyback = null;
            $bestSpread = null;
            foreach ($withBuyback as $brand => $row) {
                if ($bestBuyback === null || $row['buyback_per_gram'] > $withBuyback[$bestBuyback]['buyback_per_gram']) {
                    $bestBuyback = $brand;
                }
                if ($bestSpread === null || abs($row['spread']) < abs($withBuyback[$bestSpread]['spread'])) {
                    $bestSpread = $brand;
                }
            }
            if ($bestBuyback !== null) {
                $rows[$bestBuyback]['tags'][] = 'buyback-terbaik';
            }
            if ($bestSpread !== null && !in_array('buyback-terbaik', $rows[$bestSpread]['tags'], true)) {
                $rows[$bestSpread]['tags'][] = 'spread-tersempit';
            }
        }

        // Urutkan: data tersedia dulu, lalu brand sesuai urutan master.
        $order = array_flip(array_keys(FfrGoldPrice::BRANDS));
        uasort($rows, fn($a, $b) => ($order[$a['brand']] ?? 99) <=> ($order[$b['brand']] ?? 99));

        $available = array_keys($rows);
        $manualOnly = array_values(array_diff(array_keys(FfrGoldPrice::BRANDS), $available));

        return [
            'weight'      => $weight,
            'rows'        => array_values($rows),
            'spot'        => $spot,
            'fetched_at'  => $fetchedAt,
            'date'        => $date,
            'stale'       => $date !== null && $date < $today,
            'auto_brands' => FfrGoldPrice::AUTO_BRANDS,
            'manual_only' => array_values(array_diff($manualOnly, ['lainnya'])),
        ];
    }

    /**
     * Harga terbaru untuk satu brand + berat (dipakai tombol "Ambil Harga").
     *
     * @return array{brand:string,label:string,weight:float,price_date:string,
     *               buy:float,buyback:?float,buy_per_gram:float,
     *               buyback_per_gram:?float,source:string,is_manual:bool,
     *               fetched_at:?string,stale:bool}|null
     */
    public function priceFor(string $brand, float $weight): ?array
    {
        if (!FfrGoldPrice::isBrand($brand)) {
            return null;
        }

        $this->ensureFresh();

        $comparison = $this->comparison($weight);
        foreach ($comparison['rows'] as $row) {
            if ($row['brand'] !== $brand) {
                continue;
            }
            return [
                'brand'             => $brand,
                'label'             => $row['label'],
                'weight'            => $row['weight'],
                'price_date'        => $row['price_date'],
                'buy'               => $row['buy'],
                'buyback'           => $row['buyback'],
                'buy_per_gram'      => $row['buy_per_gram'],
                'buyback_per_gram'  => $row['buyback_per_gram'],
                'source'            => $row['source'],
                'is_manual'         => $row['is_manual'],
                'fetched_at'        => $row['fetched_at'],
                'stale'             => $row['stale'],
            ];
        }

        return null;
    }

    /**
     * Simpan harga manual (per brand) untuk satu pecahan & tanggal.
     *
     * @param array $posted brand => ['buy' => string, 'buyback' => string]
     */
    public function saveManual(array $posted, float $weight, ?string $date = null): int
    {
        $date = $date ?: date('Y-m-d');
        $weight = FfrGoldPrice::nearestWeight($weight);

        // Spot terakhir dipakai untuk menghitung premium pada harga manual.
        $comparison = $this->comparison($weight);
        $spot = $comparison['spot'];

        $saved = 0;
        foreach ($posted as $brand => $values) {
            if (!FfrGoldPrice::isBrand($brand) || !is_array($values)) {
                continue;
            }

            $buy = $this->parseMoney($values['buy'] ?? '');
            $buyback = $this->parseMoney($values['buyback'] ?? '');

            if ($buy === null && $buyback === null) {
                // Dua kolom dikosongkan = kembalikan brand ini ke harga otomatis.
                FfrGoldPrice::deleteManual($date, $brand, $weight);
                continue;
            }

            $ok = FfrGoldPrice::upsert([
                'price_date'    => $date,
                'brand_key'     => $brand,
                'weight_gram'   => $weight,
                'buy_price'     => $buy !== null ? $buy : ($buyback ?? 0),
                'buyback_price' => $buyback,
                'spot_idr_gram' => $spot['idr_gram'] ?? null,
                'spot_usd_oz'   => $spot['usd_oz'] ?? null,
                'usd_idr'       => $spot['usd_idr'] ?? null,
                'source'        => 'manual',
                'source_url'    => null,
                'is_manual'     => 1,
                'fetched_at'    => date('Y-m-d H:i:s'),
            ]);

            if ($ok) {
                $saved++;
            }
        }

        return $saved;
    }

    /**
     * Pratinjau perubahan harga semua aset emas.
     *
     * @return array<int, array> satu entri per aset (dengan flag changed)
     */
    public function bulkPreview(): array
    {
        $assets = array_values(array_filter(
            FfrSavingsAsset::all(true),
            fn($a) => ($a['type_key'] ?? '') === 'emas'
        ));

        $comparisons = [];
        $preview = [];

        foreach ($assets as $asset) {
            $brand = (string) ($asset['gold_brand'] ?? '');
            if ($brand === '') {
                $brand = $this->guessBrand((string) ($asset['institution'] ?? ''));
            }

            $weight = (float) ($asset['gold_weight_gram'] ?? 0);
            if ($weight <= 0) {
                $weight = (float) ($asset['quantity'] ?? 0);
            }
            if ($weight <= 0) {
                $weight = 1.0;
            }

            $lookupWeight = FfrGoldPrice::nearestWeight($weight);
            $weightKey = FfrGoldPrice::weightKey($lookupWeight);
            if (!isset($comparisons[$weightKey])) {
                $comparisons[$weightKey] = $this->comparison($lookupWeight);
            }

            $row = null;
            foreach ($comparisons[$weightKey]['rows'] as $candidate) {
                if ($candidate['brand'] === $brand) {
                    $row = $candidate;
                    break;
                }
            }

            $entry = [
                'id'            => (int) $asset['id'],
                'name'          => $asset['name'],
                'brand'         => $brand,
                'brand_label'   => $brand !== '' ? FfrGoldPrice::brandMeta($brand)['label'] : '—',
                'weight'        => $weight,
                'old_buy'       => (float) $asset['current_price'],
                'old_buyback'   => (float) ($asset['sell_price'] ?? 0),
                'new_buy'       => null,
                'new_buyback'   => null,
                'price_date'    => null,
                'source'        => null,
                'changed'       => false,
                'reason'        => '',
            ];

            if ($brand === '') {
                $entry['reason'] = 'Brand emas belum diisi.';
                $preview[] = $entry;
                continue;
            }
            if ($row === null) {
                $entry['reason'] = 'Belum ada harga untuk brand ini.';
                $preview[] = $entry;
                continue;
            }

            $entry['new_buy'] = round($row['buy_per_gram'], 2);
            $entry['new_buyback'] = $row['buyback_per_gram'] !== null
                ? round($row['buyback_per_gram'], 2)
                : null;
            $entry['price_date'] = $row['price_date'];
            $entry['source'] = $row['source'];

            $buyChanged = abs($entry['new_buy'] - $entry['old_buy']) >= 0.5;
            $buybackChanged = $entry['new_buyback'] !== null
                && abs($entry['new_buyback'] - $entry['old_buyback']) >= 0.5;

            $entry['changed'] = $buyChanged || $buybackChanged;
            if (!$entry['changed']) {
                $entry['reason'] = 'Harga sudah sama.';
            }

            $preview[] = $entry;
        }

        return $preview;
    }

    /**
     * Terapkan pembaruan massal untuk aset terpilih.
     *
     * @param int[] $ids
     * @return array{updated:int, skipped:int}
     */
    public function applyBulk(array $ids): array
    {
        $ids = array_map('intval', $ids);
        $updated = 0;
        $skipped = 0;

        foreach ($this->bulkPreview() as $entry) {
            if (!in_array($entry['id'], $ids, true)) {
                continue;
            }
            if (!$entry['changed'] || $entry['new_buy'] === null) {
                $skipped++;
                continue;
            }

            $ok = FfrSavingsAsset::updateGoldValuation(
                $entry['id'],
                (float) $entry['new_buy'],
                $entry['new_buyback'] !== null ? (float) $entry['new_buyback'] : (float) $entry['old_buyback'],
                (string) $entry['price_date'],
                (string) $entry['source'],
                date('Y-m-d H:i:s'),
                (string) $entry['brand'],
                FfrGoldPrice::nearestWeight((float) $entry['weight'])
            );

            $ok ? $updated++ : $skipped++;
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /** Riwayat harga beberapa hari terakhir. */
    public function history(int $days = 14, ?float $weight = null): array
    {
        return FfrGoldPrice::history($days, $weight);
    }

    /** Pilih baris untuk pecahan yang diminta; bila tidak ada, pecahan terdekat. */
    private function pickRow(array $weights, string $weightKey, float $weight): ?array
    {
        if (isset($weights[$weightKey])) {
            return $weights[$weightKey];
        }

        $best = null;
        $bestDiff = PHP_FLOAT_MAX;
        foreach ($weights as $key => $row) {
            $diff = abs((float) $key - $weight);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $row;
            }
        }

        return $best;
    }

    /** Cocokkan nama institusi ke brand (mis. "Antam" → antam). */    public function guessBrand(string $institution): string
    {
        $text = mb_strtolower($institution);
        foreach (FfrGoldPrice::BRANDS as $key => $meta) {
            if ($key === 'lainnya') {
                continue;
            }
            if ($text !== '' && mb_strpos($text, mb_strtolower($meta['label'])) !== false) {
                return $key;
            }
        }
        return '';
    }

    /** "12.345.678,90" → 12345678.9 ; kosong → null. */
    private function parseMoney(mixed $value): ?float
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $clean = preg_replace('/[^0-9,.\-]/', '', $text);
        if ($clean === '' || $clean === '-') {
            return null;
        }
        if (strpos($clean, ',') !== false) {
            return (float) str_replace(',', '.', str_replace('.', '', $clean));
        }
        // Tanpa koma: titik dianggap pemisah ribuan.
        return (float) str_replace('.', '', $clean);
    }
}

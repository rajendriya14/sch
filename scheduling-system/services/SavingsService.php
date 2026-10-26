<?php

namespace Services;

use Models\FfrSavingsAsset;
use Models\FfrSavingsSnapshot;
use Models\FfrSavingsTransaction;

/**
 * Perhitungan laman "Tabunganku": nilai tiap aset, ringkasan portofolio,
 * dan alokasi per jenis investasi.
 */
class SavingsService
{
    /**
     * Lengkapi satu baris aset dengan angka turunan.
     *
     * Modal & nilai memakai kurs bila mata uang aset bukan IDR. Untuk aset
     * berjenis "cash" (dana cash / deposito) harga per unit dianggap 1
     * sehingga nilai = saldo. Obligasi dinilai dalam persen nominal
     * (mode "percent"), jadi harga dibagi 100.
     *
     * Nilai kini (`value`) adalah perkiraan uang yang diterima bila aset
     * dijual hari ini: harga jual/buyback × jumlah, dikurangi biaya jual
     * dan pajak. `gross_value` menyimpan nilai kotor sebelum potongan.
     *
     * @return array Aset + cost, value, gross_value, gain, gain_percent, type_meta
     */
    public function valuate(array $asset): array
    {
        $type   = $asset['type_key'] ?? 'lainnya';
        $mode   = FfrSavingsAsset::valuationMode($type);
        $isCash = FfrSavingsAsset::isCash($type);

        $quantity = (float) $asset['quantity'];
        $rate     = (float) ($asset['exchange_rate'] ?? 1);
        if ($rate <= 0) {
            $rate = 1;
        }
        $divisor = $mode === 'percent' ? 100.0 : 1.0;

        $avgPrice     = $isCash ? 1.0 : (float) $asset['avg_price'];
        $currentPrice = $isCash ? 1.0 : (float) $asset['current_price'];
        $sellPrice    = $isCash ? 1.0 : (float) ($asset['sell_price'] ?? 0);
        if ($sellPrice <= 0) {
            $sellPrice = $currentPrice;
        }

        $feeBuy   = max(0.0, (float) ($asset['fee_buy'] ?? 0));
        $feeSell  = max(0.0, (float) ($asset['fee_sell'] ?? 0));
        $taxSell  = max(0.0, (float) ($asset['tax_sell'] ?? 0));

        // Modal: harga beli rata-rata + biaya beli.
        $costGross = $quantity * $avgPrice / $divisor * $rate;
        $cost      = $costGross + ($costGross * $feeBuy / 100);

        // Nilai pasar (harga kini) dan perkiraan hasil jual (harga jual).
        $grossValue = $quantity * $currentPrice / $divisor * $rate;
        $sellGross  = $quantity * $sellPrice / $divisor * $rate;
        $sellFee    = $sellGross * $feeSell / 100;
        $sellTax    = $sellGross * $taxSell / 100;
        $netSell    = $sellGross - $sellFee - $sellTax;

        // Perkiraan biaya bila membeli lagi hari ini di harga kini.
        $buyNow = $grossValue + ($grossValue * $feeBuy / 100);

        $gain = $netSell - $cost;

        $asset['cost']         = $cost;
        $asset['cost_gross']   = $costGross;
        $asset['value']        = $netSell;
        $asset['gross_value']  = $grossValue;
        $asset['sell_price_used'] = $sellPrice;
        $asset['sell_gross']   = $sellGross;
        $asset['sell_fee']     = $sellFee;
        $asset['sell_tax']     = $sellTax;
        $asset['buy_now']      = $buyNow;
        $asset['gain']         = $gain;
        $asset['gain_percent'] = $cost > 0 ? round(($gain / $cost) * 100, 2) : null;
        $asset['coupon_annual'] = $quantity * (float) ($asset['coupon_rate'] ?? 0) / 100;
        $asset['type_meta']    = FfrSavingsAsset::typeMeta($type);

        return $asset;
    }

    /**
     * Data siap-render untuk laman Tabunganku.
     *
     * @param bool       $activeOnly   Hanya aset yang ditandai tampil.
     * @param array|null $visibleTypes Batasi ke jenis ini (null = semua).
     *
     * @return array{assets:array, groups:array, total_value:float, total_cost:float,
     *               total_gain:float, total_gain_percent:float|null, count:int,
     *               best:?array, worst:?array, hidden_count:int}
     */
    public function overview(bool $activeOnly = true, ?array $visibleTypes = null): array
    {
        $rows = FfrSavingsAsset::all($activeOnly);

        $assets = [];
        $groups = [];
        $totalValue = 0.0;
        $totalGross = 0.0;
        $totalCost = 0.0;
        $totalFees = 0.0;
        $hiddenCount = 0;

        foreach ($rows as $row) {
            if ($visibleTypes !== null && !in_array($row['type_key'] ?? '', $visibleTypes, true)) {
                $hiddenCount++;
                continue;
            }

            $item = $this->valuate($row);
            $assets[] = $item;

            $key = $item['type_key'] ?: 'lainnya';
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key'        => $key,
                    'label'      => $item['type_meta']['label'],
                    'icon'       => $item['type_meta']['icon'],
                    'class'      => $item['type_meta']['class'],
                    'value'      => 0.0,
                    'gross'      => 0.0,
                    'cost'       => 0.0,
                    'gain'       => 0.0,
                    'count'      => 0,
                    'percent'    => 0.0,
                ];
            }
            $groups[$key]['value'] += $item['value'];
            $groups[$key]['gross'] += $item['gross_value'];
            $groups[$key]['cost']  += $item['cost'];
            $groups[$key]['gain']  += $item['gain'];
            $groups[$key]['count']++;

            $totalValue += $item['value'];
            $totalGross += $item['gross_value'];
            $totalCost  += $item['cost'];
            $totalFees  += $item['sell_fee'] + $item['sell_tax'];
        }

        foreach ($groups as &$group) {
            $group['gain_percent'] = $group['cost'] > 0
                ? round(($group['gain'] / $group['cost']) * 100, 2)
                : null;
            $group['percent'] = $totalValue > 0 ? round(($group['value'] / $totalValue) * 100, 1) : 0.0;
        }
        unset($group);

        // Urutkan jenis dari nilai terbesar agar alokasi mudah dibaca.
        uasort($groups, fn($a, $b) => $b['value'] <=> $a['value']);

        $totalGain = $totalValue - $totalCost;

        $withGain = array_values(array_filter($assets, fn($a) => $a['cost'] > 0 && $a['value'] > 0));
        usort($withGain, fn($a, $b) => ($b['gain_percent'] ?? 0) <=> ($a['gain_percent'] ?? 0));

        return [
            'assets'            => $assets,
            'groups'            => $groups,
            'total_value'       => $totalValue,
            'total_gross'       => $totalGross,
            'total_cost'        => $totalCost,
            'total_fees'        => $totalFees,
            'total_gain'        => $totalGain,
            'total_gain_percent' => $totalCost > 0 ? round(($totalGain / $totalCost) * 100, 2) : null,
            'count'             => count($assets),
            'hidden_count'      => $hiddenCount,
            'best'              => $withGain[0] ?? null,
            'worst'             => count($withGain) > 1 ? $withGain[count($withGain) - 1] : null,
        ];
    }

    /**
     * Data laman Dashboard Tabunganku.
     *
     * Menggabungkan portofolio saat ini dengan riwayat snapshot harian:
     *   - kartu ringkasan + perubahan terhadap snapshot sebelumnya
     *   - seri tren total nilai & modal
     *   - seri per kategori (untuk grafik bertumpuk)
     *   - perbandingan nilai per aset & per kategori
     *
     * @param array $listKeys Kategori yang ditampilkan (Master Kategori).
     * @param int   $days     Rentang hari yang ditampilkan (0 = semua).
     */
    public function dashboard(array $listKeys, int $days = 90): array
    {
        $data = $this->overview(true, $listKeys);

        // --- Seri riwayat ---
        $rows = FfrSavingsSnapshot::all($days);
        $labels = [];
        $dates = [];
        $values = [];
        $costs = [];
        $categorySeries = [];
        $categoryKeys = [];

        foreach ($rows as $row) {
            $labels[] = date('d/m', strtotime($row['snapshot_date']));
            $dates[] = $row['snapshot_date'];
            $values[] = round($row['total_value'], 2);
            $costs[]  = round($row['total_cost'], 2);

            foreach ($row['detail'] as $typeKey => $value) {
                if ($listKeys !== null && !in_array($typeKey, $listKeys, true)) {
                    continue;
                }
                $categorySeries[$typeKey][$row['snapshot_date']] = (float) $value;
                $categoryKeys[$typeKey] = true;
            }
        }

        $datasets = [];
        foreach (array_keys($categoryKeys) as $typeKey) {
            $meta = FfrSavingsAsset::typeMeta($typeKey);
            $points = [];
            foreach ($rows as $row) {
                $points[] = round($categorySeries[$typeKey][$row['snapshot_date']] ?? 0, 2);
            }
            $datasets[] = [
                'key'    => $typeKey,
                'label'  => $meta['label'],
                'icon'   => $meta['icon'],
                'class'  => $meta['class'],
                'values' => $points,
            ];
        }

        // --- Perubahan nilai ---
        $latest = $rows ? $rows[count($rows) - 1] : null;
        $previous = count($rows) > 1 ? $rows[count($rows) - 2] : null;
        $first = $rows[0] ?? null;

        $dayChange = $latest && $previous ? $latest['total_value'] - $previous['total_value'] : null;
        $dayChangePercent = $latest && $previous && $previous['total_value'] > 0
            ? round(($dayChange / $previous['total_value']) * 100, 2)
            : null;

        $rangeChange = $latest && $first && $latest !== $first
            ? $latest['total_value'] - $first['total_value']
            : null;
        $rangeChangePercent = $rangeChange !== null && $first['total_value'] > 0
            ? round(($rangeChange / $first['total_value']) * 100, 2)
            : null;

        // --- Perbandingan per aset (nilai terbesar lebih dulu) ---
        $assetsByValue = $data['assets'];
        usort($assetsByValue, fn($a, $b) => $b['value'] <=> $a['value']);
        $topAssets = array_slice($assetsByValue, 0, 10);
        // --- Aset dengan kontribusi untung/rugi terbesar ---
        // Dana cash selalu bernilai sama dengan saldonya, jadi tidak diikutkan
        // dalam sorotan "kinerja terbaik / perlu perhatian".
        $withGain = array_values(array_filter(
            $data['assets'],
            fn($a) => $a['gain_percent'] !== null && !FfrSavingsAsset::isCash($a['type_key'] ?? '')
        ));
        usort($withGain, fn($a, $b) => $b['gain'] <=> $a['gain']);

        $profitable = count(array_filter($withGain, fn($a) => $a['gain'] > 0));
        $losing = count(array_filter($withGain, fn($a) => $a['gain'] < 0));

        $groups = $data['groups'];
        $topGroup = $groups ? reset($groups) : null;

        // --- Arus dana bulan berjalan + penanda pada grafik tren ---
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $flowMonth = FfrSavingsTransaction::summary(['from' => $monthStart, 'to' => $monthEnd]);

        $events = [];
        if ($dates) {
            $eventFrom = $dates[0];
            $eventTo = $dates[count($dates) - 1];
            $indexByDate = array_flip($dates);

            foreach (FfrSavingsTransaction::flowEvents($eventFrom, $eventTo) as $event) {
                $index = $indexByDate[$event['date']] ?? null;
                if ($index === null) {
                    continue;
                }
                $events[] = [
                    'index'     => (int) $index,
                    'date'      => $event['date'],
                    'direction' => $event['direction'],
                    'amount'    => round((float) $event['amount'], 2),
                    'label'     => $event['label'],
                ];
            }
        }

        return $data + [
            'days'                => $days,
            'series'              => [
                'labels'   => $labels,
                'dates'    => $dates,
                'values'   => $values,
                'costs'    => $costs,
                'datasets' => $datasets,
            ],
            'flow_month'          => $flowMonth,
            'flow_events'         => $events,
            'snapshot_count'      => count($rows),
            'day_change'          => $dayChange,
            'day_change_percent'  => $dayChangePercent,
            'range_change'        => $rangeChange,
            'range_change_percent' => $rangeChangePercent,
            'top_assets'          => $topAssets,
            'assets_by_value'     => $assetsByValue,
            'top_asset_gain'      => $withGain[0] ?? null,
            'bottom_asset_gain'   => count($withGain) > 1 ? $withGain[count($withGain) - 1] : null,
            'profitable_count'    => $profitable,
            'losing_count'        => $losing,
            'top_group'           => $topGroup,
        ];
    }
}

<?php
/**
 * Family Financial Report — logika perhitungan & mesin rekomendasi.
 *
 * Konsep:
 *   gaji → dibelah ke BUCKET (Utama/Tambahan/Tabungan/Darurat)
 *        → tiap bucket diisi ITEM belanja (dengan frekuensi beli)
 *        → sistem menilai apakah rencana masih muat di dana bucket,
 *          lalu mengusulkan opsi tindakan bila tidak muat.
 */
namespace Services;

use Models\FfrBucket;
use Models\FfrCategory;
use Models\FfrIncome;
use Models\FfrItem;
use Models\FfrPeriod;
use Models\FfrPlan;

class FfrService
{
    /** Ambang "ketat" — sisa dana di bawah 10% alokasi. */
    private const TIGHT_RATIO = 0.10;
    /** Ambang "lewat" — realisasi di atas 110% rencana. */
    private const OVER_RATIO = 1.10;

    /** Nama bulan lengkap (Indonesia) — satu sumber untuk semua label. */
    public const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** Tahun yang bisa dipilih di pemilih periode. */
    public const YEARS = [2026, 2027];

    // ---------------------------------------------------------------
    // Periode
    // ---------------------------------------------------------------

    public function getOrCreatePeriod(?string $period): array
    {
        $period = $this->normalizePeriod($period);
        return FfrPeriod::findOrCreate($period);
    }

    public function normalizePeriod(?string $period): string
    {
        if (is_string($period) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            return $period;
        }
        return $this->currentPeriod();
    }

    public function currentPeriod(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))->format('Y-m');
    }

    /**
     * Susun periode dari pilihan terpisah (bulan + tahun) yang dikirim form.
     * Bila keduanya kosong, jatuh ke periode berjalan.
     */
    public function periodFromParts(mixed $month, mixed $year): string
    {
        $month = (int) $month;
        $year  = (int) $year;

        if ($month >= 1 && $month <= 12 && $year >= 1900 && $year <= 2999) {
            return sprintf('%04d-%02d', $year, $month);
        }

        return $this->currentPeriod();
    }

    /**
     * Daftar tahun untuk pemilih periode. Tahun di luar daftar (mis. dari data
     * lama) tetap disertakan agar pilihan yang sedang aktif tidak hilang.
     */
    public function yearOptions(string $activePeriod): array
    {
        $years = self::YEARS;
        $activeYear = (int) substr($activePeriod, 0, 4);

        if ($activeYear > 0 && !in_array($activeYear, $years, true)) {
            $years[] = $activeYear;
        }

        rsort($years);
        return $years;
    }

    /**
     * Daftar periode untuk pemilih (tidak dipakai lagi oleh view, disisakan
     * untuk kebutuhan lain seperti laporan).
     */
    public function periodOptions(): array
    {
        return FfrPeriod::recent(18);
    }

    public function isClosed(?array $period): bool
    {
        return ($period['status'] ?? 'open') === 'closed';
    }

    // ---------------------------------------------------------------
    // Perhitungan item
    // ---------------------------------------------------------------

    /**
     * Berapa kali item dibeli per bulan sesuai frekuensinya.
     */
    public function freqMultiplier(string $freqType): int
    {
        return FfrItem::FREQ_MULTIPLIER[$freqType] ?? 1;
    }

    /**
     * Biaya rencana sebulan untuk satu item = harga × jumlah × frekuensi.
     */
    public function monthlyCost(array $item): float
    {
        return (float) $item['unit_price']
            * (float) $item['qty']
            * $this->freqMultiplier((string) $item['freq_type']);
    }

    /**
     * Status satu item: rencana vs aktual.
     *
     * @return array{key:string,label:string,icon:string,class:string}
     */
    public function itemStatus(array $item): array
    {
        $plan = $this->monthlyCost($item);
        $actual = $item['actual_amount'];

        if ($actual === null || $actual === '') {
            return ['key' => 'belum', 'label' => 'Belum dibelanjakan', 'icon' => 'bi-dash-circle', 'class' => 'secondary'];
        }

        $actual = (float) $actual;

        if ($plan <= 0) {
            return ['key' => 'tercatat', 'label' => 'Tercatat', 'icon' => 'bi-check-circle', 'class' => 'success'];
        }
        if ($actual <= $plan) {
            return ['key' => 'aman', 'label' => 'Sesuai rencana', 'icon' => 'bi-check-circle', 'class' => 'success'];
        }
        if ($actual <= $plan * self::OVER_RATIO) {
            return ['key' => 'lebih', 'label' => 'Sedikit lebih', 'icon' => 'bi-exclamation-circle', 'class' => 'warning'];
        }
        return ['key' => 'lewat', 'label' => 'Lewat rencana', 'icon' => 'bi-exclamation-triangle', 'class' => 'danger'];
    }

    /**
     * Ringkasan semua bucket untuk satu periode (dipakai layar Rencana & Dashboard).
     */
    public function bucketSummaries(int $periodId): array
    {
        $buckets = FfrBucket::all();
        $plans = FfrPlan::mapByPeriod($periodId);
        $items = FfrItem::byPeriod($periodId);

        $byBucket = [];
        foreach ($items as $item) {
            $byBucket[(int) $item['bucket_id']][] = $item;
        }

        $out = [];
        foreach ($buckets as $bucket) {
            $id = (int) $bucket['id'];
            $rows = $byBucket[$id] ?? [];

            $planTotal = 0.0;
            $actualTotal = 0.0;
            foreach ($rows as $row) {
                $planTotal += $this->monthlyCost($row);
                if ($row['actual_amount'] !== null && $row['actual_amount'] !== '') {
                    $actualTotal += (float) $row['actual_amount'];
                }
            }

            $allocated = $plans[$id] ?? 0.0;
            $out[] = [
                'bucket'       => $bucket,
                'allocated'    => $allocated,
                'plan_total'   => $planTotal,
                'actual_total' => $actualTotal,
                'remaining'    => $allocated - $planTotal,
                'item_count'   => count($rows),
                'status'       => $this->bucketStatus($allocated, $planTotal),
            ];
        }

        return $out;
    }

    /**
     * Status bucket: dana vs total rencana item.
     *
     * @return array{key:string,label:string,icon:string,class:string,badge:string}
     */
    public function bucketStatus(float $allocated, float $planTotal): array
    {
        if ($planTotal <= 0) {
            return ['key' => 'kosong', 'label' => 'Belum ada item', 'icon' => 'bi-dash-circle', 'class' => 'secondary', 'badge' => 'bg-secondary'];
        }
        if ($allocated <= 0) {
            return ['key' => 'tanpa_dana', 'label' => 'Belum dialokasikan', 'icon' => 'bi-exclamation-circle', 'class' => 'warning', 'badge' => 'bg-warning text-dark'];
        }
        $ratio = $planTotal / $allocated;
        if ($ratio <= 1 - self::TIGHT_RATIO) {
            return ['key' => 'aman', 'label' => 'Aman', 'icon' => 'bi-check-circle', 'class' => 'success', 'badge' => 'bg-success'];
        }
        if ($ratio <= 1) {
            return ['key' => 'ketat', 'label' => 'Ketat', 'icon' => 'bi-exclamation-circle', 'class' => 'warning', 'badge' => 'bg-warning text-dark'];
        }
        return ['key' => 'lewat', 'label' => 'Lewat dana', 'icon' => 'bi-exclamation-triangle', 'class' => 'danger', 'badge' => 'bg-danger'];
    }

    /**
     * Data satu bucket untuk layar Daftar Belanja, lengkap dengan status per item.
     */
    public function bucketDetail(int $periodId, int $bucketId): ?array
    {
        $bucket = FfrBucket::find($bucketId);
        if (!$bucket) {
            return null;
        }

        $items = FfrItem::byPeriod($periodId, $bucketId);
        $plans = FfrPlan::mapByPeriod($periodId);
        $allocated = $plans[$bucketId] ?? 0.0;

        $planTotal = 0.0;
        $actualTotal = 0.0;
        $rows = [];
        foreach ($items as $item) {
            $plan = $this->monthlyCost($item);
            $planTotal += $plan;
            if ($item['actual_amount'] !== null && $item['actual_amount'] !== '') {
                $actualTotal += (float) $item['actual_amount'];
            }
            $item['monthly_cost'] = $plan;
            $item['status'] = $this->itemStatus($item);
            $item['freq_label'] = FfrItem::FREQ_LABEL[$item['freq_type']] ?? $item['freq_type'];
            $item['multiplier'] = $this->freqMultiplier((string) $item['freq_type']);
            $rows[] = $item;
        }

        return [
            'bucket'       => $bucket,
            'allocated'    => $allocated,
            'plan_total'   => $planTotal,
            'actual_total' => $actualTotal,
            'remaining'    => $allocated - $planTotal,
            'items'        => $rows,
            'status'       => $this->bucketStatus($allocated, $planTotal),
        ];
    }

    // ---------------------------------------------------------------
    // Mesin rekomendasi
    // ---------------------------------------------------------------

    /**
     * Susun opsi tindakan untuk bucket yang rencananya melebihi dana.
     *
     * Urutan prioritas yang ditunda: item TAMBAHAN (opsional) lebih dulu,
     * baru menyentuh dana TABUNGAN bilamana masih kurang.
     */
    public function recommendations(int $periodId): array
    {
        $summaries = $this->bucketSummaries($periodId);
        $overBuckets = array_values(array_filter(
            $summaries,
            fn(array $s) => $s['status']['key'] === 'lewat' || $s['status']['key'] === 'tanpa_dana'
        ));

        $allItems = FfrItem::byPeriod($periodId);
        $deferOptions = [];
        $nomor = 0;

        // Total kekurangan seluruh bucket — dipakai opsi simpanan & opsi terakhir.
        $totalDeficit = 0.0;
        foreach ($overBuckets as $summary) {
            $totalDeficit += max(0, $summary['plan_total'] - $summary['allocated']);
        }

        // --- Prioritas 1: tunda item (hanya untuk kelompok opsional) ---
        foreach ($overBuckets as $summary) {
            $bucket = $summary['bucket'];
            if ($bucket['type'] !== 'opsional') {
                continue;
            }

            $deficit = $summary['plan_total'] - $summary['allocated'];
            $bucketItems = array_values(array_filter(
                $allItems,
                fn(array $i) => (int) $i['bucket_id'] === (int) $bucket['id']
            ));

            $plan = $this->buildDeferPlan($bucketItems, $deficit);
            if (!$plan['items']) {
                continue;
            }

            $nomor++;
            $deferOptions[] = [
                'no'       => $nomor,
                'title'    => 'Tunda ' . count($plan['items']) . ' item di ' . $bucket['name'],
                'detail'   => 'Item paling tidak mendesak ditunda bulan ini — tabungan tidak perlu diganggu.',
                'savings'  => $plan['savings'],
                'items'    => $plan['items'],
                'effect'   => $plan['savings'] >= $deficit
                    ? $bucket['name'] . ' kembali aman'
                    : 'Masih kurang ' . number_format($deficit - $plan['savings'], 0, ',', '.'),
                'covers'   => $plan['savings'] >= $deficit,
                'severity' => $plan['savings'] >= $deficit ? 'success' : 'warning',
            ];
        }

        // --- Prioritas 2: ambil dari dana simpanan (dihitung sekali, bukan per bucket) ---
        $savingBuckets = array_values(array_filter(
            $summaries,
            fn(array $s) => ($s['bucket']['type'] ?? '') === 'simpan' && $s['allocated'] > 0
        ));

        if ($totalDeficit > 0) {
            foreach ($savingBuckets as $saving) {
                $ambil = min($totalDeficit, $saving['allocated']);
                if ($ambil <= 0) {
                    continue;
                }
                $nomor++;
                $sisa = $saving['allocated'] - $ambil;
                $deferOptions[] = [
                    'no'       => $nomor,
                    'title'    => 'Ambil dari ' . $saving['bucket']['name'],
                    'detail'   => 'Tutup seluruh kekurangan dengan memakai dana ' . $saving['bucket']['name'] . '.',
                    'savings'  => $ambil,
                    'items'    => [],
                    'effect'   => $saving['bucket']['name'] . ' jadi ' . number_format($sisa, 0, ',', '.')
                                  . ' (turun ' . number_format(($ambil / max($saving['allocated'], 1)) * 100, 1) . '%)',
                    'covers'   => $ambil >= $totalDeficit,
                    'severity' => 'warning',
                ];
            }
        }

        // --- Prioritas 3: biarkan ---
        if ($totalDeficit > 0) {
            $nomor++;
            $deferOptions[] = [
                'no'       => $nomor,
                'title'    => 'Biarkan (pakai uang di luar rencana)',
                'detail'   => 'Rencana tetap seperti ini; selisihnya menekan kas bulan ini.',
                'savings'  => 0,
                'items'    => [],
                'effect'   => 'Kas bulan ini tekor ' . number_format($totalDeficit, 0, ',', '.'),
                'covers'   => false,
                'severity' => 'danger',
            ];
        }

        return [
            'summaries'     => $summaries,
            'over_buckets'  => $overBuckets,
            'options'       => $deferOptions,
            'total_deficit' => $totalDeficit,
            'is_healthy'    => empty($overBuckets),
        ];
    }

    /**
     * Pilih item yang paling layak ditunda sampai kekurangan tertutup.
     * Urutan: item tanpa prioritas dulu, lalu termahal lebih dulu.
     */
    private function buildDeferPlan(array $items, float $deficit): array
    {
        $scored = [];
        foreach ($items as $item) {
            $cost = $this->monthlyCost($item);
            if ($cost <= 0) {
                continue;
            }
            $scored[] = ['item' => $item, 'cost' => $cost, 'priority' => (int) $item['is_priority']];
        }

        usort($scored, function (array $a, array $b) {
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority']; // tanpa prioritas dulu
            }
            return $b['cost'] <=> $a['cost'];             // lalu termahal
        });

        $picked = [];
        $savings = 0.0;
        foreach ($scored as $row) {
            if ($savings >= $deficit) {
                break;
            }
            $picked[] = [
                'name'  => $row['item']['name'],
                'cost'  => $row['cost'],
                'freq'  => FfrItem::FREQ_LABEL[$row['item']['freq_type']] ?? '',
                'priority' => $row['priority'],
            ];
            $savings += $row['cost'];
        }

        return ['items' => $picked, 'savings' => $savings];
    }

    // ---------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------

    public function dashboard(int $periodId): array
    {
        $summaries = $this->bucketSummaries($periodId);

        $income = FfrIncome::total($periodId);
        $allocated = FfrPlan::total($periodId);
        $savingPlan = FfrPlan::totalSaving($periodId);

        $planTotal = 0.0;
        $actualTotal = 0.0;
        $actualSaving = 0.0;
        foreach ($summaries as $s) {
            $planTotal += $s['plan_total'];
            $actualTotal += $s['actual_total'];
            if (($s['bucket']['type'] ?? '') === 'simpan') {
                $actualSaving += $s['actual_total'];
            }
        }

        // Pengeluaran riil = aktual item di bucket non-simpan.
        $expenseActual = 0.0;
        foreach ($summaries as $s) {
            if (($s['bucket']['type'] ?? '') !== 'simpan') {
                $expenseActual += $s['actual_total'];
            }
        }

        return [
            'income'          => $income,
            'allocated'       => $allocated,
            'unallocated'     => $income - $allocated,
            'plan_total'      => $planTotal,
            'actual_total'    => $actualTotal,
            'expense_actual'  => $expenseActual,
            'saving_plan'     => $savingPlan,
            'saving_actual'   => $actualSaving,
            'sisa_kas'        => $income - $expenseActual - $actualSaving,
            'summaries'       => $summaries,
            'has_plan'        => $allocated > 0,
        ];
    }

    /**
     * Data trend beberapa bulan terakhir untuk grafik dashboard.
     */
    public function trend(int $currentPeriodId, int $months = 6): array
    {
        $current = FfrPeriod::find($currentPeriodId);
        $base = $current['period'] ?? date('Y-m');

        $out = [];
        $cursor = \DateTimeImmutable::createFromFormat('Y-m', $base) ?: new \DateTimeImmutable('first day of this month');

        for ($i = $months - 1; $i >= 0; $i--) {
            $label = $cursor->modify('-' . $i . ' month')->format('Y-m');
            $period = FfrPeriod::findByPeriod($label);

            if (!$period) {
                $out[] = [
                    'period' => $label,
                    'label'  => $this->periodLabel($label),
                    'income' => 0.0, 'expense' => 0.0, 'saving' => 0.0, 'plan' => 0.0,
                    'exists' => false,
                ];
                continue;
            }

            $id = (int) $period['id'];
            $summary = $this->bucketSummaries($id);
            $expense = 0.0;
            $saving = 0.0;
            $plan = 0.0;
            foreach ($summary as $s) {
                $plan += $s['plan_total'];
                if (($s['bucket']['type'] ?? '') === 'simpan') {
                    $saving += $s['actual_total'];
                } else {
                    $expense += $s['actual_total'];
                }
            }

            $out[] = [
                'period' => $label,
                'label'  => $this->periodLabel($label),
                'income' => FfrIncome::total($id),
                'expense' => $expense,
                'saving' => $saving,
                'plan'   => $plan,
                'exists' => true,
            ];
        }

        return $out;
    }

    /**
     * Perbandingan bulan ini vs bulan lalu per kategori (naik/turun).
     */
    public function categoryComparison(int $periodId): array
    {
        $current = FfrPeriod::find($periodId);
        if (!$current) {
            return [];
        }
        $prevLabel = (new \DateTimeImmutable($current['period'] . '-01'))->modify('-1 month')->format('Y-m');
        $prev = FfrPeriod::findByPeriod($prevLabel);

        $currentMap = $this->expenseByCategory($periodId);
        $prevMap = $prev ? $this->expenseByCategory((int) $prev['id']) : [];

        $names = array_unique(array_merge(array_keys($currentMap), array_keys($prevMap)));
        $rows = [];
        foreach ($names as $name) {
            $now = $currentMap[$name] ?? 0.0;
            $before = $prevMap[$name] ?? 0.0;
            $diff = $now - $before;
            $rows[] = [
                'name'     => $name,
                'current'  => $now,
                'previous' => $before,
                'diff'     => $diff,
                'percent'  => $before > 0 ? round(($diff / $before) * 100, 1) : ($now > 0 ? null : 0.0),
                'direction' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat'),
            ];
        }

        usort($rows, fn(array $a, array $b) => abs($b['diff']) <=> abs($a['diff']));
        return array_slice($rows, 0, 8);
    }

    /**
     * Total realisasi pengeluaran per kategori dalam satu periode.
     * Kalau item belum ada realisasi, dipakai angka rencananya.
     */
    public function expenseByCategory(int $periodId): array
    {
        $rows = FfrItem::byPeriod($periodId);
        $out = [];
        foreach ($rows as $row) {
            $category = $row['category_name'] ?: 'Tanpa kategori';
            $amount = ($row['actual_amount'] !== null && $row['actual_amount'] !== '')
                ? (float) $row['actual_amount']
                : $this->monthlyCost($row);
            $out[$category] = ($out[$category] ?? 0.0) + $amount;
        }
        arsort($out);
        return $out;
    }

    /**
     * Item dengan biaya bulanan terbesar.
     */
    public function topItems(int $periodId, int $limit = 5): array
    {
        $rows = FfrItem::byPeriod($periodId);
        $out = [];
        foreach ($rows as $row) {
            $row['monthly_cost'] = $this->monthlyCost($row);
            if ($row['monthly_cost'] > 0) {
                $out[] = $row;
            }
        }
        usort($out, fn(array $a, array $b) => $b['monthly_cost'] <=> $a['monthly_cost']);
        return array_slice($out, 0, $limit);
    }

    /**
     * Kalimat insight sederhana untuk dashboard.
     */
    public function insights(int $periodId): array
    {
        $out = [];
        $comparison = $this->categoryComparison($periodId);

        foreach ($comparison as $row) {
            if ($row['percent'] === null || abs($row['diff']) < 1000) {
                continue;
            }
            $arah = $row['diff'] > 0 ? 'naik' : 'turun';
            $out[] = [
                'text' => $row['name'] . ' ' . $arah . ' ' . number_format(abs($row['percent']), 1) . '% dari bulan lalu',
                'class' => $row['diff'] > 0 ? 'danger' : 'success',
            ];
            if (count($out) >= 3) {
                break;
            }
        }

        $recommendation = $this->recommendations($periodId);
        if (!$recommendation['is_healthy'] && $recommendation['total_deficit'] > 0) {
            array_unshift($out, [
                'text'  => 'Ada rencana yang melebihi dana sebesar ' . number_format($recommendation['total_deficit'], 0, ',', '.') . ' — cek layar Rekomendasi',
                'class' => 'warning',
            ]);
        }

        return $out;
    }

    /**
     * Ledger — ringkasan lengkap satu bulan: pemasukan, alokasi, dan seluruh
     * item belanja dalam satu halaman (untuk dibaca ulang, dicetak, atau
     * diekspor).
     */
    public function ledger(int $periodId): array
    {
        $summaries = $this->bucketSummaries($periodId);
        $items = FfrItem::byPeriod($periodId);
        $incomes = FfrIncome::byPeriod($periodId);

        // Lengkapi tiap item dengan biaya bulanan, label frekuensi, dan status.
        $rowsByBucket = [];
        $grandPlan = 0.0;
        $grandActual = 0.0;
        foreach ($items as $item) {
            $plan = $this->monthlyCost($item);
            $actual = ($item['actual_amount'] !== null && $item['actual_amount'] !== '')
                ? (float) $item['actual_amount']
                : null;

            $grandPlan += $plan;
            if ($actual !== null) {
                $grandActual += $actual;
            }

            $item['monthly_cost'] = $plan;
            $item['actual_value'] = $actual;
            $item['variance'] = $actual === null ? null : $actual - $plan;
            $item['status'] = $this->itemStatus($item);
            $item['freq_label'] = FfrItem::FREQ_LABEL[$item['freq_type']] ?? $item['freq_type'];
            $item['multiplier'] = $this->freqMultiplier((string) $item['freq_type']);

            $rowsByBucket[(int) $item['bucket_id']][] = $item;
        }

        $sections = [];
        $grandAllocated = 0.0;
        foreach ($summaries as $summary) {
            $bucketId = (int) $summary['bucket']['id'];
            $rows = $rowsByBucket[$bucketId] ?? [];

            $actualTotal = 0.0;
            $actualCount = 0;
            foreach ($rows as $row) {
                if ($row['actual_value'] !== null) {
                    $actualTotal += $row['actual_value'];
                    $actualCount++;
                }
            }

            $grandAllocated += $summary['allocated'];

            $sections[] = [
                'bucket'       => $summary['bucket'],
                'allocated'    => $summary['allocated'],
                'plan_total'   => $summary['plan_total'],
                'actual_total' => $actualTotal,
                'actual_count' => $actualCount,
                'remaining'    => $summary['allocated'] - $summary['plan_total'],
                'variance'     => $actualCount > 0 ? $actualTotal - $summary['plan_total'] : null,
                'status'       => $summary['status'],
                'items'        => $rows,
                'item_count'   => count($rows),
            ];
        }

        $income = FfrIncome::total($periodId);

        return [
            'incomes'          => $incomes,
            'sections'         => $sections,
            'income'           => $income,
            'allocated'        => $grandAllocated,
            'unallocated'      => $income - $grandAllocated,
            'plan_total'       => $grandPlan,
            'actual_total'     => $grandActual,
            'item_count'       => count($items),
            'has_data'         => $income > 0 || !empty($items),
        ];
    }

    public function periodLabel(string $period): string
    {
        $parts = explode('-', $period);
        if (count($parts) !== 2) {
            return $period;
        }
        $nama = self::MONTHS[(int) $parts[1]] ?? $parts[1];
        return mb_substr($nama, 0, 3) . ' ' . $parts[0];
    }

    public function periodLabelLong(string $period): string
    {
        $parts = explode('-', $period);
        if (count($parts) !== 2) {
            return $period;
        }
        return (self::MONTHS[(int) $parts[1]] ?? $parts[1]) . ' ' . $parts[0];
    }

    // ---------------------------------------------------------------
    // Salin dari bulan sebelumnya
    // ---------------------------------------------------------------

    public function previousPeriodLabel(string $period): string
    {
        return (new \DateTimeImmutable($period . '-01'))->modify('-1 month')->format('Y-m');
    }

    /**
     * Salin item + alokasi dari bulan sebelumnya (realisasi dikosongkan).
     *
     * @return array{success:bool,items:int,plans:int,error:string}
     */
    public function copyFromPrevious(int $periodId, string $period): array
    {
        $prevLabel = $this->previousPeriodLabel($period);
        $prev = FfrPeriod::findByPeriod($prevLabel);

        if (!$prev) {
            return ['success' => false, 'items' => 0, 'plans' => 0, 'error' => 'Belum ada data bulan ' . $this->periodLabelLong($prevLabel) . '.'];
        }

        $items = FfrItem::copyFrom((int) $prev['id'], $periodId);
        $plans = FfrPlan::copyFrom((int) $prev['id'], $periodId);

        return ['success' => true, 'items' => $items, 'plans' => $plans, 'error' => ''];
    }

    /**
     * Daftar item untuk dipilih pada dropdown pemindahan bucket.
     */
    public function categoryOptions(): array
    {
        return FfrCategory::all();
    }
}

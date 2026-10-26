<?php
/**
 * FFR — Tabunganku: Dashboard.
 *
 * Menampilkan total aset, tren nilai dari snapshot harian, dan perbandingan
 * antar aset/kategori dalam bentuk grafik.
 *
 * Variabel: $data (SavingsService::dashboard), $types, $range
 */
use Helpers\FormHelper;
use Helpers\MoneyHelper;
use Models\FfrSavingsAsset;

$pageName = $pageName ?? 'ffr';
$ffrActive = 'savings_dashboard';
$ffrSubmenu = 'savings';
require __DIR__ . '/_nav.php';

$series = $data['series'] ?? ['labels' => [], 'values' => [], 'costs' => [], 'datasets' => []];
$hasTrend = count($series['labels']) >= 2;
$range = $range ?? 90;
$rankedAssets = $data['assets_by_value'] ?? $data['top_assets'] ?? [];
$ranges = [30 => '30 hari', 90 => '90 hari', 180 => '6 bulan', 365 => '1 tahun', 0 => 'Semua'];
$gainClass = fn(?float $gain): string => $gain === null || abs($gain) < 1 ? 'text-muted' : ($gain > 0 ? 'text-success' : 'text-danger');

// Warna grafik mengikuti kelas Bootstrap tiap jenis aset.
$palette = [
    'success' => '#198754', 'primary' => '#0d6efd', 'warning' => '#f0ad4e',
    'danger'  => '#dc3545', 'info'    => '#0dcaf0', 'secondary' => '#6c757d',
    'dark'    => '#212529', 'purple'  => '#6f42c1', 'teal' => '#20c997',
];
$colorFor = fn(string $class): string => $palette[$class] ?? '#6c757d';

// Data grafik dikirim sebagai JSON agar tidak perlu request tambahan.
$chartPayload = [
    'trend' => [
        'labels' => $series['labels'],
        'values' => $series['values'],
        'costs'  => $series['costs'],
        'events' => $data['flow_events'] ?? [],
    ],
    'allocation' => [
        'labels' => array_map(fn($g) => $g['label'], array_values($data['groups'])),
        'values' => array_map(fn($g) => round($g['value'], 2), array_values($data['groups'])),
        'colors' => array_map(fn($g) => $colorFor($g['class']), array_values($data['groups'])),
    ],
    'assets' => [
        'labels' => array_map(fn($a) => $a['name'], $data['top_assets']),
        'values' => array_map(fn($a) => round($a['value'], 2), $data['top_assets']),
        'gains'  => array_map(fn($a) => $a['gain'], $data['top_assets']),
    ],
    'category' => [
        'labels' => array_map(fn($g) => $g['label'], array_values($data['groups'])),
        'values' => array_map(fn($g) => round($g['value'], 2), array_values($data['groups'])),
        'costs'  => array_map(fn($g) => round($g['cost'], 2), array_values($data['groups'])),
        'colors' => array_map(fn($g) => $colorFor($g['class']), array_values($data['groups'])),
    ],
    'categoryTrend' => [
        'labels'   => $series['labels'],
        'datasets' => array_map(fn($d) => [
            'label'  => $d['label'],
            'values' => $d['values'],
            'color'  => $colorFor($d['class']),
        ], $series['datasets']),
    ],
];
?>

<!-- Toolbar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="btn-group btn-group-sm" role="group" aria-label="Rentang waktu">
        <?php foreach ($ranges as $days => $label): ?>
            <a href="index.php?page=ffr&action=savingsDashboard&range=<?= (int) $days ?>"
               class="btn btn-outline-dark <?= (int) $range === (int) $days ? 'active' : '' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?page=ffr&action=savings" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-wallet2"></i> Portofolio
        </a>
        <form method="post" action="index.php?page=ffr&action=saveSavingsSnapshot" class="d-inline">
            <?= FormHelper::csrfField() ?>
            <button type="submit" class="btn btn-sm btn-dark">
                <i class="bi bi-camera"></i> Simpan Snapshot Hari Ini
            </button>
        </form>
    </div>
</div>

<!-- Kartu ringkasan -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="card h-100 sv-kpi">
            <div class="card-body">
                <div class="sv-kpi-label"><i class="bi bi-wallet2"></i> Total Nilai Aset</div>
                <div class="sv-kpi-value"><?= MoneyHelper::rupiah($data['total_value']) ?></div>
                <div class="sv-kpi-foot">
                    <?php if ($data['day_change'] !== null): ?>
                        <span class="<?= $gainClass($data['day_change']) ?>">
                            <i class="bi bi-<?= $data['day_change'] >= 0 ? 'arrow-up-right' : 'arrow-down-right' ?>"></i>
                            <?= ($data['day_change'] >= 0 ? '+' : '−') . MoneyHelper::rupiah(abs($data['day_change'])) ?>
                            (<?= ($data['day_change_percent'] ?? 0) >= 0 ? '+' : '' ?><?= MoneyHelper::percentText($data['day_change_percent'], 2) ?>%)
                        </span>
                        <span class="text-muted">vs snapshot sebelumnya</span>
                    <?php else: ?>
                        <span class="text-muted">Belum ada pembanding</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="card h-100 sv-kpi">
            <div class="card-body">
                <div class="sv-kpi-label"><i class="bi bi-piggy-bank"></i> Total Modal</div>
                <div class="sv-kpi-value"><?= MoneyHelper::rupiah($data['total_cost']) ?></div>
                <div class="sv-kpi-foot">
                    <span class="text-muted"><?= (int) $data['count'] ?> aset aktif · <?= (int) $data['snapshot_count'] ?> snapshot</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="card h-100 sv-kpi">
            <div class="card-body">
                <div class="sv-kpi-label"><i class="bi bi-graph-up-arrow"></i> Potensi Untung / Rugi</div>
                <div class="sv-kpi-value <?= $gainClass($data['total_gain']) ?>">
                    <?= $data['total_gain'] < 0 ? '−' : '+' ?><?= MoneyHelper::rupiah(abs($data['total_gain'])) ?>
                </div>
                <div class="sv-kpi-foot <?= $gainClass($data['total_gain']) ?>">
                    <?= $data['total_gain_percent'] === null
                        ? 'belum ada modal'
                        : ($data['total_gain_percent'] > 0 ? '+' : '') . MoneyHelper::percentText($data['total_gain_percent'], 2) . '% dari modal' ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="card h-100 sv-kpi">
            <div class="card-body">
                <div class="sv-kpi-label"><i class="bi bi-pie-chart"></i> Jenis Investasi</div>
                <div class="sv-kpi-value"><?= count($data['groups']) ?> <span class="fs-6 text-muted">jenis</span></div>
                <div class="sv-kpi-foot">
                    <?php if ($data['range_change'] !== null): ?>
                        <span class="<?= $gainClass($data['range_change']) ?>">
                            <?= ($data['range_change'] >= 0 ? '+' : '−') . MoneyHelper::rupiah(abs($data['range_change'])) ?>
                            (<?= ($data['range_change_percent'] ?? 0) >= 0 ? '+' : '' ?><?= MoneyHelper::percentText($data['range_change_percent'], 2) ?>%)
                        </span>
                        <span class="text-muted">sepanjang periode</span>
                    <?php else: ?>
                        <span class="text-muted"><?= (int) $data['profitable_count'] ?> naik · <?= (int) $data['losing_count'] ?> turun</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Arus dana bulan berjalan -->
<div class="card mb-3">
    <div class="card-body py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="small">
            <i class="bi bi-arrow-left-right text-muted"></i>
            <strong>Arus dana bulan ini</strong>
            <span class="text-success ms-2">Masuk <?= MoneyHelper::rupiah($data['flow_month']['in'] ?? 0) ?></span>
            <span class="text-muted mx-1">·</span>
            <span class="text-danger">Keluar <?= MoneyHelper::rupiah($data['flow_month']['out'] ?? 0) ?></span>
            <span class="text-muted mx-1">·</span>
            <span class="text-primary">Pindah <?= MoneyHelper::rupiah($data['flow_month']['transfer'] ?? 0) ?></span>
            <span class="text-muted mx-1">·</span>
            <span class="<?= $gainClass($data['flow_month']['realized'] ?? null) ?>">
                Realisasi <?= ($data['flow_month']['realized'] ?? 0) < 0 ? '−' : '+' ?><?= MoneyHelper::rupiah(abs($data['flow_month']['realized'] ?? 0)) ?>
            </span>
        </div>
        <a href="index.php?page=ffr&action=savingsMutations" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-journal-arrow-down"></i> Lihat Mutasi
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Tren nilai -->
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header sv-card-head d-flex justify-content-between align-items-center">
                <span><i class="bi bi-activity"></i> Tren Nilai Portofolio</span>
                <span class="small text-muted">Nilai kini vs modal</span>
            </div>
            <div class="card-body">
                <?php if (!$hasTrend): ?>
                    <div class="sv-empty">
                        <i class="bi bi-graph-up"></i>
                        <p class="mb-1"><strong>Tren akan terbentuk otomatis.</strong></p>
                        <p class="mb-0 small">
                            Dashboard menyimpan snapshot nilai portofolio setiap hari Anda membuka
                            Tabunganku. Setelah ada minimal dua hari data, grafik tren akan tampil di sini.
                            Klik <strong>Simpan Snapshot Hari Ini</strong> untuk mencatat titik awal.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="sv-chart sv-chart-lg">
                        <canvas id="svChartTrend"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Alokasi -->
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header sv-card-head">
                <span><i class="bi bi-pie-chart"></i> Alokasi per Jenis</span>
            </div>
            <div class="card-body">
                <?php if (empty($data['groups'])): ?>
                    <div class="sv-empty">
                        <i class="bi bi-inbox"></i>
                        <p class="mb-0 small">Belum ada aset untuk ditampilkan.</p>
                    </div>
                <?php else: ?>
                    <div class="sv-chart sv-chart-donut">
                        <canvas id="svChartAllocation"></canvas>
                        <div class="sv-donut-center">
                            <span class="sv-donut-label">Total</span>
                            <strong><?= MoneyHelper::short($data['total_value']) ?></strong>
                        </div>
                    </div>
                    <ul class="sv-legend mt-3">
                        <?php foreach ($data['groups'] as $group): ?>
                            <li>
                                <span class="sv-legend-dot" style="background: <?= $colorFor($group['class']) ?>"></span>
                                <span class="sv-legend-label">
                                    <i class="bi <?= htmlspecialchars($group['icon']) ?>"></i>
                                    <?= htmlspecialchars($group['label']) ?>
                                </span>
                                <span class="sv-legend-value">
                                    <?= MoneyHelper::short($group['value']) ?>
                                    <small class="text-muted"><?= MoneyHelper::percentText($group['percent']) ?>%</small>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <!-- Perbandingan per aset -->
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header sv-card-head d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart-line"></i> Perbandingan Nilai per Aset</span>
                <span class="small text-muted">Top <?= count($data['top_assets']) ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($data['top_assets'])): ?>
                    <div class="sv-empty">
                        <i class="bi bi-inbox"></i>
                        <p class="mb-0 small">Tambahkan aset di halaman Portofolio untuk melihat perbandingan.</p>
                    </div>
                <?php else: ?>
                    <div class="sv-chart sv-chart-bar">
                        <canvas id="svChartAssets"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Nilai vs modal per kategori -->
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header sv-card-head d-flex justify-content-between align-items-center">
                <span><i class="bi bi-columns-gap"></i> Nilai vs Modal</span>
                <span class="small text-muted">per jenis</span>
            </div>
            <div class="card-body">
                <?php if (empty($data['groups'])): ?>
                    <div class="sv-empty">
                        <i class="bi bi-inbox"></i>
                        <p class="mb-0 small">Belum ada data.</p>
                    </div>
                <?php else: ?>
                    <div class="sv-chart sv-chart-bar">
                        <canvas id="svChartCategory"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <!-- Tren per kategori -->
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header sv-card-head d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up"></i> Tren Nilai per Jenis</span>
                <span class="small text-muted">bertumpuk</span>
            </div>
            <div class="card-body">
                <?php if (!$hasTrend || empty($series['datasets'])): ?>
                    <div class="sv-empty">
                        <i class="bi bi-bar-chart"></i>
                        <p class="mb-0 small">
                            Grafik ini muncul setelah ada minimal dua snapshot harian dan aset
                            pada kategori yang ditampilkan.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="sv-chart sv-chart-lg">
                        <canvas id="svChartCategoryTrend"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Insight -->
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header sv-card-head">
                <span><i class="bi bi-lightbulb"></i> Insight Singkat</span>
            </div>
            <div class="card-body">
                <ul class="sv-insights">
                    <li>
                        <span class="sv-insight-icon bg-success-subtle text-success"><i class="bi bi-trophy"></i></span>
                        <div>
                            <div class="sv-insight-title">Kinerja terbaik</div>
                            <?php if ($data['top_asset_gain']): ?>
                                <div class="small">
                                    <strong><?= htmlspecialchars($data['top_asset_gain']['name']) ?></strong>
                                    · <span class="<?= $gainClass($data['top_asset_gain']['gain']) ?>">
                                        <?= $data['top_asset_gain']['gain'] >= 0 ? '+' : '−' ?><?= MoneyHelper::rupiah(abs($data['top_asset_gain']['gain'])) ?>
                                        (<?= $data['top_asset_gain']['gain_percent'] > 0 ? '+' : '' ?><?= MoneyHelper::percentText($data['top_asset_gain']['gain_percent'], 2) ?>%)
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="small text-muted">Belum ada pergerakan.</div>
                            <?php endif; ?>
                        </div>
                    </li>
                    <li>
                        <span class="sv-insight-icon bg-danger-subtle text-danger"><i class="bi bi-arrow-down-right"></i></span>
                        <div>
                            <div class="sv-insight-title">Perlu perhatian</div>
                            <?php if ($data['bottom_asset_gain']): ?>
                                <div class="small">
                                    <strong><?= htmlspecialchars($data['bottom_asset_gain']['name']) ?></strong>
                                    · <span class="<?= $gainClass($data['bottom_asset_gain']['gain']) ?>">
                                        <?= $data['bottom_asset_gain']['gain'] >= 0 ? '+' : '−' ?><?= MoneyHelper::rupiah(abs($data['bottom_asset_gain']['gain'])) ?>
                                        (<?= $data['bottom_asset_gain']['gain_percent'] > 0 ? '+' : '' ?><?= MoneyHelper::percentText($data['bottom_asset_gain']['gain_percent'], 2) ?>%)
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="small text-muted">Belum ada pembanding.</div>
                            <?php endif; ?>
                        </div>
                    </li>
                    <li>
                        <span class="sv-insight-icon bg-primary-subtle text-primary"><i class="bi bi-pie-chart"></i></span>
                        <div>
                            <div class="sv-insight-title">Porsi terbesar</div>
                            <?php if ($data['top_group']): ?>
                                <div class="small">
                                    <strong><?= htmlspecialchars($data['top_group']['label']) ?></strong>
                                    · <?= MoneyHelper::percentText($data['top_group']['percent']) ?>% dari total
                                </div>
                            <?php else: ?>
                                <div class="small text-muted">Belum ada aset.</div>
                            <?php endif; ?>
                        </div>
                    </li>
                    <li>
                        <span class="sv-insight-icon bg-dark-subtle text-dark"><i class="bi bi-check2-circle"></i></span>
                        <div>
                            <div class="sv-insight-title">Status aset</div>
                            <div class="small">
                                <?= (int) $data['profitable_count'] ?> naik ·
                                <?= (int) $data['losing_count'] ?> turun ·
                                <?= (int) max(0, $data['count'] - $data['profitable_count'] - $data['losing_count']) ?> stabil
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Peringkat aset -->
<div class="card mt-3">
    <div class="card-header sv-card-head d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ol"></i> Peringkat Aset</span>
        <span class="small text-muted">diurutkan dari nilai terbesar</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 52px;">#</th>
                        <th style="min-width: 200px;">Aset</th>
                        <th style="min-width: 130px;">Jenis</th>
                        <th class="text-end" style="min-width: 130px;">Nilai Kini</th>
                        <th class="text-end" style="min-width: 100px;">Porsi</th>
                        <th class="text-end" style="min-width: 150px;">Untung / Rugi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rankedAssets)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Belum ada aset. Tambahkan lewat halaman Portofolio.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rankedAssets as $i => $asset): ?>
                            <?php
                            $share = $data['total_value'] > 0 ? ($asset['value'] / $data['total_value']) * 100 : 0;
                            $cash = FfrSavingsAsset::isCash($asset['type_key']);
                            $meta = $asset['type_meta'];
                            ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($asset['name']) ?></strong>
                                    <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($asset['owner']) ?></span>
                                    <?php if (!empty($asset['institution'])): ?>
                                        <small class="text-muted d-block"><?= htmlspecialchars($asset['institution']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-dark-subtle text-dark border border-dark">
                                        <i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i>
                                        <?= htmlspecialchars($meta['label']) ?>
                                    </span>
                                </td>
                                <td class="text-end"><strong><?= MoneyHelper::rupiah($asset['value']) ?></strong></td>
                                <td class="text-end">
                                    <?= MoneyHelper::percentText($share) ?>%
                                    <div class="progress sv-mini-progress">
                                        <div class="progress-bar bg-dark" style="width: <?= round($share) ?>%"></div>
                                    </div>
                                </td>
                                <td class="text-end <?= $gainClass($asset['gain']) ?>">
                                    <?php if ($cash): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <?= $asset['gain'] < 0 ? '−' : '+' ?><?= MoneyHelper::rupiah(abs($asset['gain'])) ?>
                                        <?php if ($asset['gain_percent'] !== null): ?>
                                            <small class="d-block">
                                                <?= $asset['gain_percent'] > 0 ? '+' : '' ?><?= MoneyHelper::percentText($asset['gain_percent'], 2) ?>%
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle"></i>
    Snapshot nilai disimpan otomatis sekali per hari saat halaman Tabunganku dibuka.
    Grafik memakai kategori yang aktif di <a href="index.php?page=ffr&action=savingsMaster">Master Kategori</a>.
</p>

<?php require __DIR__ . '/_nav_end.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    window.SV_DASHBOARD = <?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="scheduling-system/public/assets/js/savings_dashboard.js?v=<?= @filemtime(__DIR__ . '/../../public/assets/js/savings_dashboard.js') ?: '1' ?>"></script>

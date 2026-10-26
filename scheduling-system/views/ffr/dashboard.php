<?php
/**
 * FFR — Dashboard.
 *
 * Variabel: $period, $periodLabel, $periodOptions, $dashboard, $trend,
 *           $comparison, $topItems, $insights, $recommend, $hasAnyData
 */
use Helpers\MoneyHelper;

$pageName = $pageName ?? 'ffr';
$ffrActive = 'dashboard';
require __DIR__ . '/_nav.php';

$d = $dashboard;
$maxBar = 0.0;
foreach ($trend as $t) {
    $maxBar = max($maxBar, (float) $t['income'], (float) $t['expense']);
}
$monthsWithData = count(array_filter($trend, fn($t) => $t['exists'] && ($t['income'] > 0 || $t['expense'] > 0)));
?>

<?php if (!$hasAnyData): ?>
    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="bi bi-info-circle fs-5"></i>
        <div>
            <strong>Belum ada data untuk <?= htmlspecialchars($periodLabel) ?>.</strong><br>
            Mulai dari <a href="index.php?page=ffr&action=plan&period=<?= urlencode($period['period']) ?>">Rencana Bulan</a>
            untuk mengisi pemasukan &amp; alokasi dana, lalu tambahkan item belanja di
            <a href="index.php?page=ffr&action=items&period=<?= urlencode($period['period']) ?>">Daftar Belanja</a>.
        </div>
    </div>
<?php endif; ?>

<?php if ($insights): ?>
    <div class="mb-3">
        <?php foreach ($insights as $insight): ?>
            <div class="ffr-insight text-<?= htmlspecialchars($insight['class']) ?>">
                <i class="bi bi-lightbulb"></i> <?= htmlspecialchars($insight['text']) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Kartu ringkasan -->
<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="card h-100 ffr-card">
            <div class="card-body">
                <div class="ffr-card-label"><i class="bi bi-arrow-down-circle text-success"></i> Pemasukan</div>
                <h3 class="ffr-card-value"><?= MoneyHelper::rupiah($d['income']) ?></h3>
                <small class="text-muted">
                    <?php if ($d['has_plan']): ?>
                        Terpakai alokasi <?= MoneyHelper::percent($d['allocated'], $d['income']) ?>%
                    <?php else: ?>
                        Alokasi belum diisi
                    <?php endif; ?>
                </small>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card h-100 ffr-card">
            <div class="card-body">
                <div class="ffr-card-label"><i class="bi bi-cart-dash text-danger"></i> Pengeluaran (realisasi)</div>
                <h3 class="ffr-card-value"><?= MoneyHelper::rupiah($d['expense_actual']) ?></h3>
                <small class="text-muted">Rencana belanja <?= MoneyHelper::rupiah($d['plan_total']) ?></small>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card h-100 ffr-card">
            <div class="card-body">
                <div class="ffr-card-label"><i class="bi bi-piggy-bank text-primary"></i> Tabungan (tersetor)</div>
                <h3 class="ffr-card-value"><?= MoneyHelper::rupiah($d['saving_actual']) ?></h3>
                <small class="text-muted">
                    Target <?= MoneyHelper::rupiah($d['saving_plan']) ?>
                    <?php if ($d['saving_plan'] > 0): ?>
                        · <?= MoneyHelper::percent($d['saving_actual'], $d['saving_plan']) ?>%
                    <?php endif; ?>
                </small>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card h-100 ffr-card">
            <div class="card-body">
                <div class="ffr-card-label"><i class="bi bi-wallet text-dark"></i> Sisa kas</div>
                <h3 class="ffr-card-value <?= $d['sisa_kas'] < 0 ? 'text-danger' : '' ?>">
                    <?= MoneyHelper::rupiah($d['sisa_kas']) ?>
                </h3>
                <small class="text-muted">Setelah pengeluaran &amp; setoran</small>
            </div>
        </div>
    </div>
</div>

<!-- Bucket -->
<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-diagram-3"></i> Dana per Kelompok</span>
                <a class="btn btn-sm btn-outline-light" href="index.php?page=ffr&action=plan&period=<?= urlencode($period['period']) ?>">
                    Atur Alokasi
                </a>
            </div>
            <div class="card-body">
                <?php foreach ($d['summaries'] as $s): ?>
                    <?php
                        $alloc = $s['allocated'];
                        $plan = $s['plan_total'];
                        $pct = $alloc > 0 ? min(100, MoneyHelper::percent($plan, $alloc, 1)) : ($plan > 0 ? 100 : 0);
                        $barClass = match ($s['status']['key']) {
                            'aman' => 'bg-success',
                            'ketat' => 'bg-warning',
                            'lewat', 'tanpa_dana' => 'bg-danger',
                            default => 'bg-secondary',
                        };
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($s['bucket']['name']) ?></strong>
                                <span class="badge <?= $s['status']['badge'] ?> ms-1">
                                    <i class="bi <?= $s['status']['icon'] ?>"></i> <?= $s['status']['label'] ?>
                                </span>
                            </div>
                            <div class="text-end small">
                                <strong><?= MoneyHelper::rupiah($plan) ?></strong>
                                <span class="text-muted">/ <?= MoneyHelper::rupiah($alloc) ?></span>
                            </div>
                        </div>
                        <div class="progress ffr-progress mt-1">
                            <div class="progress-bar <?= $barClass ?>" style="width: <?= $pct ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-muted">
                            <span><?= (int) $s['item_count'] ?> item
                                <?php if ($s['actual_total'] > 0): ?>
                                    · realisasi <?= MoneyHelper::rupiah($s['actual_total']) ?>
                                <?php endif; ?>
                            </span>
                            <span>
                                <?php $sisa = $s['remaining']; ?>
                                <?= $sisa < 0 ? 'Kurang ' . MoneyHelper::rupiah(abs($sisa)) : 'Sisa ' . MoneyHelper::rupiah($sisa) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="border-top pt-2 d-flex justify-content-between">
                    <span class="text-muted">Belum dialokasikan</span>
                    <strong class="<?= $d['unallocated'] < 0 ? 'text-danger' : '' ?>">
                        <?= MoneyHelper::rupiah($d['unallocated']) ?>
                    </strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-bar-chart"></i> Trend 6 Bulan
            </div>
            <div class="card-body">
                <?php if ($monthsWithData <= 0): ?>
                    <p class="text-muted mb-0 small">
                        Belum ada data untuk digambar. Grafik akan terisi setelah ada pemasukan/pengeluaran tercatat.
                    </p>
                <?php else: ?>
                    <div class="ffr-chart">
                        <?php foreach ($trend as $t): ?>
                            <?php
                                $hIn = $maxBar > 0 ? max(2, round(((float) $t['income'] / $maxBar) * 100)) : 0;
                                $hOut = $maxBar > 0 ? max(2, round(((float) $t['expense'] / $maxBar) * 100)) : 0;
                            ?>
                            <div class="ffr-chart-col" title="<?= htmlspecialchars($t['label']) ?>: masuk <?= MoneyHelper::rupiah($t['income']) ?>, keluar <?= MoneyHelper::rupiah($t['expense']) ?>">
                                <div class="ffr-chart-bars">
                                    <div class="ffr-bar ffr-bar-in" style="height: <?= $t['income'] > 0 ? $hIn : 0 ?>%"></div>
                                    <div class="ffr-bar ffr-bar-out" style="height: <?= $t['expense'] > 0 ? $hOut : 0 ?>%"></div>
                                </div>
                                <div class="ffr-chart-label"><?= htmlspecialchars(str_replace(' 20', ' ', $t['label'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex gap-3 small mt-2">
                        <span><span class="ffr-legend ffr-bar-in"></span> Pemasukan</span>
                        <span><span class="ffr-legend ffr-bar-out"></span> Pengeluaran</span>
                    </div>
                    <?php if ($monthsWithData < 3): ?>
                        <p class="text-muted small mb-0 mt-2">
                            <i class="bi bi-info-circle"></i> Baru <?= $monthsWithData ?> bulan berisi data — trend mulai terbaca setelah 3 bulan.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Naik/turun per kategori -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-graph-up-arrow"></i> Naik / Turun per Kategori
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kategori</th>
                                <th class="text-end">Bulan Lalu</th>
                                <th class="text-end">Bulan Ini</th>
                                <th class="text-end">Selisih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($comparison)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Belum ada data kategori.</td></tr>
                            <?php else: ?>
                                <?php foreach ($comparison as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td class="text-end"><?= MoneyHelper::rupiah($row['previous']) ?></td>
                                        <td class="text-end"><?= MoneyHelper::rupiah($row['current']) ?></td>
                                        <td class="text-end">
                                            <?php if ($row['direction'] === 'flat'): ?>
                                                <span class="text-muted">—</span>
                                            <?php else: ?>
                                                <span class="<?= $row['direction'] === 'up' ? 'text-danger' : 'text-success' ?>">
                                                    <i class="bi bi-arrow-<?= $row['direction'] === 'up' ? 'up' : 'down' ?>-right"></i>
                                                    <?= MoneyHelper::rupiah(abs($row['diff'])) ?>
                                                    <?php if ($row['percent'] !== null): ?>
                                                        (<?= abs($row['percent']) ?>%)
                                                    <?php endif; ?>
                                                </span>
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
    </div>

    <!-- Top item + rekomendasi -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-trophy"></i> Item Terbesar Bulan Ini
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Kelompok</th>
                                <th class="text-end">Per Bulan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topItems)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">
                                    Belum ada item. <a href="index.php?page=ffr&action=items&period=<?= urlencode($period['period']) ?>">Tambah item belanja</a>
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($topItems as $item): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($item['name']) ?>
                                            <?php if ($item['category_name']): ?>
                                                <small class="text-muted d-block"><?= htmlspecialchars($item['category_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?= htmlspecialchars($item['bucket_name']) ?></small></td>
                                        <td class="text-end"><strong><?= MoneyHelper::rupiah($item['monthly_cost']) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">
                <?php if ($recommend['is_healthy']): ?>
                    <span class="text-success"><i class="bi bi-check-circle"></i> Semua rencana masih dalam dana. Aman.</span>
                <?php else: ?>
                    <span class="text-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        Rencana melebihi dana <?= MoneyHelper::rupiah($recommend['total_deficit']) ?>
                    </span>
                    <a class="btn btn-sm btn-outline-dark float-end"
                       href="index.php?page=ffr&action=recommendation&period=<?= urlencode($period['period']) ?>">
                        Lihat Rekomendasi
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_nav_end.php'; ?>

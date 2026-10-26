<?php
/**
 * FFR — Rekomendasi: opsi tindakan saat rencana melebihi dana.
 *
 * Variabel: $period, $periodLabel, $periodOptions, $data
 */
use Helpers\MoneyHelper;

$pageName = $pageName ?? 'ffr';
$ffrActive = 'recommendation';
require __DIR__ . '/_nav.php';

$summaries = $data['summaries'];
$options = $data['options'];
$overBuckets = $data['over_buckets'];
$totalDeficit = $data['total_deficit'];
?>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-clipboard-data"></i> Kondisi Dana vs Rencana
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Kelompok</th>
                                <th class="text-end">Dana</th>
                                <th class="text-end">Rencana</th>
                                <th class="text-end">Selisih</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summaries as $s): ?>
                                <?php $selisih = $s['allocated'] - $s['plan_total']; ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($s['bucket']['name']) ?></strong>
                                        <small class="text-muted d-block"><?= (int) $s['item_count'] ?> item</small>
                                    </td>
                                    <td class="text-end"><?= MoneyHelper::rupiah($s['allocated']) ?></td>
                                    <td class="text-end"><?= MoneyHelper::rupiah($s['plan_total']) ?></td>
                                    <td class="text-end <?= $selisih < 0 ? 'text-danger fw-semibold' : 'text-success' ?>">
                                        <?= $selisih < 0 ? '−' . MoneyHelper::rupiah(abs($selisih)) : MoneyHelper::rupiah($selisih) ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $s['status']['badge'] ?>">
                                            <i class="bi <?= $s['status']['icon'] ?>"></i> <?= $s['status']['label'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">
                <?php if ($data['is_healthy']): ?>
                    <span class="text-success">
                        <i class="bi bi-check-circle"></i> Semua kelompok masih dalam dana — tidak ada tindakan yang diperlukan.
                    </span>
                <?php else: ?>
                    <span class="text-danger fw-semibold">
                        <i class="bi bi-exclamation-triangle"></i>
                        Total kekurangan <?= MoneyHelper::rupiah($totalDeficit) ?> dari
                        <?= count($overBuckets) ?> kelompok
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-signpost-split"></i> Ringkasan
            </div>
            <div class="card-body">
                <?php if ($data['is_healthy']): ?>
                    <p class="mb-2"><i class="bi bi-emoji-smile text-success"></i> Rencana bulan ini sehat.</p>
                    <p class="text-muted small mb-0">
                        Lanjutkan mengisi realisasi di <a href="index.php?page=ffr&action=items&period=<?= urlencode($period['period']) ?>">Daftar Belanja</a>,
                        dan pantau <a href="index.php?page=ffr&action=index&period=<?= urlencode($period['period']) ?>">Dashboard</a>.
                    </p>
                <?php else: ?>
                    <p class="mb-2">Sistem menyiapkan <strong><?= count($options) ?> opsi</strong> tindakan. Pilih salah satu:</p>
                    <ul class="small text-muted mb-0">
                        <?php foreach ($overBuckets as $s): ?>
                            <li>
                                <strong><?= htmlspecialchars($s['bucket']['name']) ?></strong> kurang
                                <strong class="text-danger"><?= MoneyHelper::rupiah($s['plan_total'] - $s['allocated']) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!$data['is_healthy']): ?>
    <h5 class="mb-2"><i class="bi bi-lightbulb"></i> Opsi Tindakan</h5>
    <div class="row g-3">
        <?php foreach ($options as $opt): ?>
            <div class="col-lg-6">
                <div class="card h-100 ffr-option ffr-option-<?= htmlspecialchars($opt['severity']) ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <span class="badge bg-dark me-1">Opsi <?= (int) $opt['no'] ?></span>
                                <strong><?= htmlspecialchars($opt['title']) ?></strong>
                                <?php if ($opt['covers']): ?>
                                    <span class="badge bg-success-subtle text-success border border-success ms-1">
                                        <i class="bi bi-check-circle"></i> Menutup kekurangan
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($opt['savings'] > 0): ?>
                                <div class="text-end text-nowrap">
                                    <div class="small text-muted">Efek</div>
                                    <strong><?= MoneyHelper::rupiah($opt['savings']) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>

                        <p class="text-muted small mb-2 mt-2"><?= htmlspecialchars($opt['detail']) ?></p>

                        <?php if (!empty($opt['items'])): ?>
                            <ul class="small mb-2 ffr-option-items">
                                <?php foreach ($opt['items'] as $item): ?>
                                    <li>
                                        <?= htmlspecialchars($item['name']) ?>
                                        <span class="text-muted">(<?= htmlspecialchars($item['freq']) ?>)</span>
                                        — <?= MoneyHelper::rupiah($item['cost']) ?>
                                        <?php if ($item['priority']): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger">prioritas</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <div class="ffr-option-effect">
                            <i class="bi bi-arrow-right-circle"></i> <?= htmlspecialchars($opt['effect']) ?>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <a class="btn btn-sm btn-outline-dark"
                           href="index.php?page=ffr&action=items&period=<?= urlencode($period['period']) ?>">
                            <i class="bi bi-pencil-square"></i> Sesuaikan manual di Daftar Belanja
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="text-muted small mt-3 mb-0">
        <i class="bi bi-info-circle"></i>
        Opsi di atas adalah usulan sistem, bukan tindakan otomatis. Setelah memilih,
        sesuaikan itemnya di Daftar Belanja atau ubah alokasi di Rencana Bulan — barulah angkanya berubah.
    </p>
<?php endif; ?>
<?php require __DIR__ . '/_nav_end.php'; ?>

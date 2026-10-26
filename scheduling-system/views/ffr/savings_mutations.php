<?php
/**
 * FFR — Tabunganku: Mutasi.
 *
 * Riwayat transaksi aset (setor, tarik/jual, transfer, koreksi) beserta
 * ringkasan arus dana bulan berjalan.
 *
 * Variabel: $transactions, $summary, $allAssets, $month, $type, $assetId
 */
use Helpers\FormHelper;
use Helpers\MoneyHelper;
use Models\FfrSavingsAsset;
use Models\FfrSavingsTransaction;

$pageName = $pageName ?? 'ffr';
$ffrActive = 'savings_mutations';
$ffrSubmenu = 'savings';
require __DIR__ . '/_nav.php';

$gainClass = fn(?float $value): string => $value === null || abs($value) < 1
    ? 'text-muted'
    : ($value > 0 ? 'text-success' : 'text-danger');

$query = ['month' => $month, 'type' => $type, 'asset_id' => $assetId ?: ''];
$exportQuery = http_build_query(array_filter($query, fn($v) => $v !== '' && $v !== null));

$archived = array_values(array_filter($allAssets, fn($a) => !empty($a['archived_at']) || (int) $a['is_active'] === 0));
$monthLabel = date('F Y', strtotime($month . '-01'));
?>

<!-- Toolbar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" action="index.php" class="d-flex flex-wrap align-items-end gap-2">
        <input type="hidden" name="page" value="ffr">
        <input type="hidden" name="action" value="savingsMutations">
        <div>
            <label for="mvMonth" class="form-label mb-0 small text-muted">Bulan</label>
            <input type="month" class="form-control form-control-sm" id="mvMonth" name="month" value="<?= htmlspecialchars($month) ?>">
        </div>
        <div>
            <label for="mvType" class="form-label mb-0 small text-muted">Jenis</label>
            <select class="form-select form-select-sm" id="mvType" name="type">
                <option value="">Semua</option>
                <?php foreach (FfrSavingsTransaction::TYPES as $key => $meta): ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= $type === $key ? 'selected' : '' ?>>
                        <?= htmlspecialchars($meta['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="mvAsset" class="form-label mb-0 small text-muted">Aset</label>
            <select class="form-select form-select-sm" id="mvAsset" name="asset_id">
                <option value="">Semua aset</option>
                <?php foreach ($allAssets as $asset): ?>
                    <option value="<?= (int) $asset['id'] ?>" <?= $assetId === (int) $asset['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($asset['name']) ?>
                        <?= empty($asset['archived_at']) ? '' : ' (arsip)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-dark">
            <i class="bi bi-funnel"></i> Terapkan
        </button>
    </form>

    <div class="d-flex flex-wrap gap-2">
        <a href="index.php?page=ffr&action=exportSavingsMutations&<?= htmlspecialchars($exportQuery) ?>"
           class="btn btn-sm btn-outline-dark">
            <i class="bi bi-filetype-csv"></i> Ekspor CSV
        </a>
        <button type="button" class="btn btn-sm btn-outline-dark" onclick="window.print()">
            <i class="bi bi-printer"></i> Cetak
        </button>
        <a href="index.php?page=ffr&action=savings" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-wallet2"></i> Portofolio
        </a>
    </div>
</div>

<!-- Ringkasan arus dana -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="card h-100 sv-kpi">
            <div class="card-body">
                <div class="sv-kpi-label"><i class="bi bi-arrow-down-circle"></i> Dana Masuk</div>
                <div class="sv-kpi-value text-success"><?= MoneyHelper::rupiah($summary['in']) ?></div>
                <div class="sv-kpi-foot">setoran / tambahan aset</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card h-100 sv-kpi">
            <div class="card-body">
                <div class="sv-kpi-label"><i class="bi bi-arrow-up-circle"></i> Dana Keluar</div>
                <div class="sv-kpi-value text-danger"><?= MoneyHelper::rupiah($summary['out']) ?></div>
                <div class="sv-kpi-foot">ditarik / dijual (konsumsi)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card h-100 sv-kpi">
            <div class="card-body">
                <div class="sv-kpi-label"><i class="bi bi-arrow-left-right"></i> Pindah Aset</div>
                <div class="sv-kpi-value text-primary"><?= MoneyHelper::rupiah($summary['transfer']) ?></div>
                <div class="sv-kpi-foot">bukan pengeluaran — hanya berpindah bentuk</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card h-100 sv-kpi">
            <div class="card-body">
                <div class="sv-kpi-label"><i class="bi bi-graph-up-arrow"></i> Realisasi</div>
                <div class="sv-kpi-value <?= $gainClass($summary['realized']) ?>">
                    <?= $summary['realized'] < 0 ? '−' : '+' ?><?= MoneyHelper::rupiah(abs($summary['realized'])) ?>
                </div>
                <div class="sv-kpi-foot">untung/rugi dari aset yang dijual</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel mutasi -->
<div class="card">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-journal-arrow-down"></i> Mutasi <?= htmlspecialchars($monthLabel) ?></span>
        <span class="small"><?= (int) $summary['count'] ?> transaksi</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 100px;">Tanggal</th>
                        <th style="min-width: 190px;">Aset</th>
                        <th style="min-width: 130px;">Jenis</th>
                        <th class="text-end" style="min-width: 110px;">Jumlah</th>
                        <th class="text-end" style="min-width: 120px;">Harga</th>
                        <th class="text-end" style="min-width: 125px;">Nilai</th>
                        <th class="text-end" style="min-width: 125px;">Realisasi</th>
                        <th class="text-end" style="min-width: 110px;">Saldo</th>
                        <th style="min-width: 180px;">Tujuan / Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                Belum ada transaksi pada bulan ini.
                                Gunakan tombol <strong>Tarik/Jual</strong> atau <strong>Setor</strong>
                                di halaman Portofolio.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                            <?php
                            $meta = FfrSavingsTransaction::typeMeta($tx['tx_type']);
                            $direction = FfrSavingsTransaction::isOut($tx['tx_type']) ? 'out'
                                : (FfrSavingsTransaction::isIn($tx['tx_type']) ? 'in' : 'neutral');
                            $assetType = $tx['asset_type'] ?? 'lainnya';
                            $isCash = FfrSavingsAsset::isCash($assetType);
                            ?>
                            <tr>
                                <td class="text-nowrap"><?= htmlspecialchars(date('d/m/Y', strtotime($tx['tx_date']))) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($tx['asset_name'] ?? '—') ?></strong>
                                    <?php if (!empty($tx['counterpart_name'])): ?>
                                        <small class="text-muted d-block">
                                            <?= $direction === 'out' ? '→' : '←' ?>
                                            <?= htmlspecialchars($tx['counterpart_name']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= htmlspecialchars($meta['class']) ?>-subtle text-dark border">
                                        <i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i>
                                        <?= htmlspecialchars($meta['label']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if ($isCash): ?>
                                        Rp <?= number_format($tx['quantity'], 2, ',', '.') ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars(rtrim(rtrim(number_format($tx['quantity'], 4, ',', '.'), '0'), ',')) ?>
                                        <small class="text-muted"><?= htmlspecialchars((string) ($tx['asset_unit'] ?? '')) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?= $isCash ? '—' : MoneyHelper::rupiah($tx['price']) ?>
                                </td>
                                <td class="text-end <?= $direction === 'out' ? 'text-danger' : ($direction === 'in' ? 'text-success' : '') ?>">
                                    <?= $direction === 'out' ? '−' : ($direction === 'in' ? '+' : '') ?>
                                    <?= MoneyHelper::rupiah($tx['amount']) ?>
                                    <?php if ($tx['fee'] + $tx['tax'] > 0): ?>
                                        <small class="text-muted d-block">
                                            biaya <?= MoneyHelper::rupiah($tx['fee'] + $tx['tax']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end <?= $gainClass($tx['realized_gain']) ?>">
                                    <?php if ($tx['realized_gain'] === null || ($isCash && abs($tx['realized_gain']) < 0.5)): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <?= $tx['realized_gain'] < 0 ? '−' : '+' ?><?= MoneyHelper::rupiah(abs($tx['realized_gain'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-muted">
                                    <?= htmlspecialchars(rtrim(rtrim(number_format($tx['balance_after'], 4, ',', '.'), '0'), ',')) ?>
                                </td>
                                <td class="small">
                                    <?php if (!empty($tx['category'])): ?>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars(FfrSavingsTransaction::categoryLabel($tx['category'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($tx['note'])): ?>
                                        <span class="text-muted d-block"><?= htmlspecialchars($tx['note']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white small text-muted">
        <i class="bi bi-info-circle"></i>
        Saldo aset = saldo awal + seluruh transaksi. Nilai "Dana Keluar" adalah uang yang
        benar-benar meninggalkan portofolio; "Pindah Aset" tidak mengurangi total kekayaan.
    </div>
</div>

<?php if (!empty($archived)): ?>
<div class="card mt-3">
    <div class="card-header bg-dark text-white">
        <i class="bi bi-archive"></i> Aset Diarsipkan — <?= count($archived) ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 220px;">Aset</th>
                        <th style="min-width: 130px;">Jenis</th>
                        <th class="text-end">Saldo</th>
                        <th style="min-width: 130px;">Diarsipkan</th>
                        <th class="text-end" style="min-width: 110px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archived as $asset): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($asset['name']) ?></strong>
                                <?php if (!empty($asset['institution'])): ?>
                                    <small class="text-muted d-block"><?= htmlspecialchars($asset['institution']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(FfrSavingsAsset::typeMeta($asset['type_key'])['label']) ?></td>
                            <td class="text-end">
                                <?= htmlspecialchars(rtrim(rtrim(number_format((float) $asset['quantity'], 4, ',', '.'), '0'), ',')) ?>
                                <small class="text-muted"><?= htmlspecialchars($asset['unit_label']) ?></small>
                            </td>
                            <td class="small text-muted">
                                <?= !empty($asset['archived_at'])
                                    ? htmlspecialchars(date('d/m/Y H:i', strtotime($asset['archived_at'])))
                                    : '—' ?>
                            </td>
                            <td class="text-end">
                                <a href="index.php?page=ffr&action=restoreSavings&id=<?= (int) $asset['id'] ?>"
                                   class="btn btn-sm btn-outline-secondary"
                                   data-confirm="Aktifkan kembali aset &quot;<?= htmlspecialchars($asset['name']) ?>&quot;?">
                                    <i class="bi bi-arrow-counterclockwise"></i> Aktifkan
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_nav_end.php'; ?>

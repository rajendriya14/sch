<?php
/**
 * FFR — Ledger: seluruh data satu bulan dalam satu halaman.
 *
 * Variabel: $period, $periodLabel, $isClosed, $ledger
 */
use Helpers\MoneyHelper;

$pageName = $pageName ?? 'ffr';
$ffrActive = 'ledger';
require __DIR__ . '/_nav.php';

$periodValue = $period['period'];
$link = fn(string $action): string => 'index.php?page=ffr&action=' . $action . '&period=' . urlencode($periodValue);

$sections = $ledger['sections'];
$isHealthy = true;
foreach ($sections as $s) {
    if (in_array($s['status']['key'], ['lewat', 'tanpa_dana'], true)) {
        $isHealthy = false;
        break;
    }
}

// Teks ringkas untuk dibagikan ke WhatsApp / aplikasi lain.
$shareText = implode("\n", [
    '*Ledger Family Financial Report*',
    $periodLabel,
    '',
    'Pemasukan: ' . MoneyHelper::rupiah($ledger['income']),
    'Dialokasikan: ' . MoneyHelper::rupiah($ledger['allocated']),
    'Rencana item: ' . MoneyHelper::rupiah($ledger['plan_total']),
    'Realisasi: ' . MoneyHelper::rupiah($ledger['actual_total']),
    'Jumlah item: ' . (int) $ledger['item_count'] . ' item',
]);
?>

<!-- Toolbar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 ledger-toolbar">
    <div>
        <h3 class="h5 mb-0">
            <i class="bi bi-journal-text"></i> Ledger <?= htmlspecialchars($periodLabel) ?>
        </h3>
        <p class="text-muted mb-0 small">
            Seluruh pemasukan, alokasi, dan item belanja bulan ini dalam satu tampilan.
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-sm btn-dark" id="ledgerShare"
                data-period="<?= htmlspecialchars($periodLabel, ENT_QUOTES) ?>"
                data-file="ledger-ffr-<?= htmlspecialchars($periodValue, ENT_QUOTES) ?>"
                data-text="<?= htmlspecialchars($shareText, ENT_QUOTES) ?>">
            <i class="bi bi-share-fill"></i> Bagikan
        </button>
        <a href="<?= $link('exportLedger') ?>" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-filetype-csv"></i> Ekspor CSV
        </a>
        <button type="button" class="btn btn-sm btn-outline-dark" id="ledgerPrint">
            <i class="bi bi-printer"></i> Cetak
        </button>
    </div>
</div>

<div id="ledgerCapture">
    <!-- Kepala ledger: hanya tampil saat dicetak atau saat dirender jadi
         PDF/gambar, supaya hasil bagikan tetap punya identitas & periode. -->
    <div class="ledger-export-head">
        <div class="ledger-export-brand"><i class="bi bi-piggy-bank"></i> Family Financial Report</div>
        <div class="ledger-export-title">Ledger <?= htmlspecialchars($periodLabel) ?></div>
        <div class="ledger-export-meta" id="ledgerExportStamp"></div>
    </div>

<?php if (!$ledger['has_data']): ?>
    <div class="alert alert-info d-flex gap-2">
        <i class="bi bi-info-circle fs-5"></i>
        <div>
            <strong>Belum ada data untuk <?= htmlspecialchars($periodLabel) ?>.</strong><br>
            Isi dulu lewat <a href="<?= $link('plan') ?>">Rencana Bulan</a> dan
            <a href="<?= $link('items') ?>">Daftar Belanja</a>, lalu kembali ke halaman ini untuk melihat rekapnya.
        </div>
    </div>
<?php endif; ?>

<!-- ============ RINGKASAN ============ -->
<div class="card mb-3">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clipboard-data"></i> Ringkasan</span>
        <?php if ($isClosed): ?>
            <span class="badge bg-light text-dark"><i class="bi bi-lock"></i> Bulan ditutup</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 ledger-table">
                <tbody>
                    <tr>
                        <th>Total pemasukan</th>
                        <td class="text-end"><?= MoneyHelper::rupiah($ledger['income']) ?></td>
                        <th>Total dialokasikan</th>
                        <td class="text-end"><?= MoneyHelper::rupiah($ledger['allocated']) ?></td>
                        <th>Belum dialokasikan</th>
                        <td class="text-end <?= $ledger['unallocated'] < 0 ? 'text-danger fw-semibold' : '' ?>">
                            <?= MoneyHelper::rupiah($ledger['unallocated']) ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Total rencana item</th>
                        <td class="text-end"><?= MoneyHelper::rupiah($ledger['plan_total']) ?></td>
                        <th>Total realisasi</th>
                        <td class="text-end"><?= MoneyHelper::rupiah($ledger['actual_total']) ?></td>
                        <th>Jumlah item</th>
                        <td class="text-end"><?= (int) $ledger['item_count'] ?> item</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============ PEMASUKAN ============ -->
<div class="card mb-3">
    <div class="card-header bg-dark text-white">
        <i class="bi bi-arrow-down-circle"></i> Pemasukan
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 ledger-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 44px;">No</th>
                        <th>Sumber</th>
                        <th style="width: 130px;">Pemilik</th>
                        <th class="text-end" style="width: 160px;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledger['incomes'])): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Belum ada pemasukan bulan ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ledger['incomes'] as $i => $income): ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($income['source']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($income['owner']) ?></span>
                                </td>
                                <td class="text-end"><?= MoneyHelper::rupiah($income['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td colspan="3" class="text-end">Total pemasukan</td>
                        <td class="text-end"><?= MoneyHelper::rupiah($ledger['income']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- ============ ALOKASI PER KELOMPOK ============ -->
<div class="card mb-3">
    <div class="card-header bg-dark text-white">
        <i class="bi bi-diagram-3"></i> Alokasi &amp; Realisasi per Kelompok
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 ledger-table">
                <thead class="table-light">
                    <tr>
                        <th>Kelompok</th>
                        <th class="text-end">Dana</th>
                        <th class="text-end">Rencana</th>
                        <th class="text-end">Realisasi</th>
                        <th class="text-end">Sisa Dana</th>
                        <th class="text-center" style="width: 130px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sections)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">Belum ada kelompok dana.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sections as $s): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($s['bucket']['name']) ?></strong>
                                    <small class="text-muted d-block"><?= (int) $s['item_count'] ?> item</small>
                                </td>
                                <td class="text-end"><?= MoneyHelper::rupiah($s['allocated']) ?></td>
                                <td class="text-end"><?= MoneyHelper::rupiah($s['plan_total']) ?></td>
                                <td class="text-end">
                                    <?= $s['actual_count'] > 0 ? MoneyHelper::rupiah($s['actual_total']) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td class="text-end <?= $s['remaining'] < 0 ? 'text-danger fw-semibold' : '' ?>">
                                    <?= $s['remaining'] < 0 ? '−' . MoneyHelper::rupiah(abs($s['remaining'])) : MoneyHelper::rupiah($s['remaining']) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $s['status']['badge'] ?>">
                                        <i class="bi <?= $s['status']['icon'] ?>"></i> <?= $s['status']['label'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td>Total</td>
                        <td class="text-end"><?= MoneyHelper::rupiah($ledger['allocated']) ?></td>
                        <td class="text-end"><?= MoneyHelper::rupiah($ledger['plan_total']) ?></td>
                        <td class="text-end"><?= MoneyHelper::rupiah($ledger['actual_total']) ?></td>
                        <td class="text-end"><?= MoneyHelper::rupiah($ledger['allocated'] - $ledger['plan_total']) ?></td>
                        <td class="text-center">
                            <?php if ($isHealthy): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Aman</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> Ada yang lewat</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- ============ DETAIL ITEM PER KELOMPOK ============ -->
<?php foreach ($sections as $s): ?>
    <div class="card mb-3 ledger-section">
        <div class="card-header bg-dark text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>
                <i class="bi bi-list-check"></i> <?= htmlspecialchars($s['bucket']['name']) ?>
                <span class="text-white-50 small ms-1"><?= (int) $s['item_count'] ?> item</span>
            </span>
            <span class="small">
                Dana <?= MoneyHelper::rupiah($s['allocated']) ?> ·
                Rencana <?= MoneyHelper::rupiah($s['plan_total']) ?> ·
                Realisasi <?= $s['actual_count'] > 0 ? MoneyHelper::rupiah($s['actual_total']) : '—' ?>
                <span class="badge <?= $s['status']['badge'] ?> ms-1"><?= $s['status']['label'] ?></span>
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 ledger-table ffr-items-table">
                    <colgroup>
                        <col class="ffr-col-no">
                        <col class="ffr-col-item">
                        <col class="ffr-col-cat">
                        <col class="ffr-col-freq">
                        <col class="ffr-col-price">
                        <col class="ffr-col-qty">
                        <col class="ffr-col-month">
                        <col class="ffr-col-actual">
                        <col class="ffr-col-variance">
                        <col class="ffr-col-status">
                    </colgroup>
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Item</th>
                            <th>Kategori</th>
                            <th>Frekuensi</th>
                            <th class="text-end">Harga</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Per Bulan</th>
                            <th class="text-end">Aktual</th>
                            <th class="text-end">Selisih</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($s['items'])): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-3">
                                    Belum ada item di kelompok ini.
                                    <a href="<?= $link('items') ?>&bucket=<?= (int) $s['bucket']['id'] ?>">Tambah item</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($s['items'] as $i => $item): ?>
                                <tr>
                                    <td class="text-muted"><?= $i + 1 ?></td>
                                    <td>
                                        <span class="ffr-item-name" title="<?= htmlspecialchars($item['name'] . (!empty($item['notes']) ? ' — ' . $item['notes'] : '') . (!empty($item['is_priority']) ? ' · Prioritas' : '')) ?>">
                                            <?= htmlspecialchars($item['name']) ?><?php if (!empty($item['is_priority'])): ?><i class="bi bi-pin-angle-fill text-danger ms-1 ffr-pin"
                                                   title="Prioritas — wajib / tidak bisa ditunda"></i><?php endif; ?>
                                        </span>
                                    </td>
                                    <td><small><?= htmlspecialchars($item['category_name'] ?? '—') ?></small></td>
                                    <td>
                                        <small>
                                            <?= htmlspecialchars($item['freq_label']) ?>
                                            <?php if ($item['multiplier'] > 0): ?>
                                                <span class="text-muted">×<?= (int) $item['multiplier'] ?></span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td class="text-end"><?= MoneyHelper::rupiah($item['unit_price']) ?></td>
                                    <td class="text-end"><?= rtrim(rtrim(number_format((float) $item['qty'], 2, ',', '.'), '0'), ',') ?></td>
                                    <td class="text-end"><?= MoneyHelper::rupiah($item['monthly_cost']) ?></td>
                                    <td class="text-end">
                                        <?= $item['actual_value'] === null ? '<span class="text-muted">—</span>' : MoneyHelper::rupiah($item['actual_value']) ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($item['variance'] === null): ?>
                                            <span class="text-muted">—</span>
                                        <?php elseif (abs($item['variance']) < 1): ?>
                                            <span class="text-muted">0</span>
                                        <?php else: ?>
                                            <span class="<?= $item['variance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                                <?= $item['variance'] > 0 ? '+' : '−' ?><?= MoneyHelper::rupiah(abs($item['variance'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $item['status']['class'] ?>-subtle text-<?= $item['status']['class'] ?> border border-<?= $item['status']['class'] ?>">
                                            <i class="bi <?= $item['status']['icon'] ?>"></i> <?= $item['status']['label'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($s['items'])): ?>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td colspan="6" class="text-end">Subtotal <?= htmlspecialchars($s['bucket']['name']) ?></td>
                                <td class="text-end"><?= MoneyHelper::rupiah($s['plan_total']) ?></td>
                                <td class="text-end"><?= $s['actual_count'] > 0 ? MoneyHelper::rupiah($s['actual_total']) : '—' ?></td>
                                <td class="text-end">
                                    <?php if ($s['variance'] === null): ?>
                                        —
                                    <?php else: ?>
                                        <span class="<?= $s['variance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                            <?= $s['variance'] > 0 ? '+' : '−' ?><?= MoneyHelper::rupiah(abs($s['variance'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<p class="text-muted small">
    <i class="bi bi-info-circle"></i>
    Kolom <strong>Selisih</strong> = Aktual − Rencana (merah bila lebih besar, hijau bila lebih hemat).
    Tanda <strong>—</strong> berarti realisasi belum diisi.
</p>
</div><!-- /#ledgerCapture -->

<!-- ============ MODAL BAGIKAN ============ -->
<div class="modal fade" id="ledgerShareModal" tabindex="-1" aria-labelledby="ledgerShareModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ledgerShareModalLabel">
                    <i class="bi bi-share-fill"></i> Bagikan Ledger
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="small text-muted">Format berkas</span>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Format berkas">
                        <input type="radio" class="btn-check" name="ledgerShareFmt" id="ledgerFmtPdf" value="pdf" checked>
                        <label class="btn btn-outline-dark" for="ledgerFmtPdf">
                            <i class="bi bi-filetype-pdf"></i> PDF
                        </label>
                        <input type="radio" class="btn-check" name="ledgerShareFmt" id="ledgerFmtPng" value="png">
                        <label class="btn btn-outline-dark" for="ledgerFmtPng">
                            <i class="bi bi-file-earmark-image"></i> Gambar PNG
                        </label>
                    </div>
                    <span class="small text-muted ms-auto" id="ledgerShareInfo"></span>
                </div>

                <div class="ledger-share-preview" id="ledgerSharePreview">
                    <div class="text-center text-muted py-5">
                        <span class="spinner-border spinner-border-sm"></span> Menyiapkan pratinjau…
                    </div>
                </div>

                <p class="text-muted small mb-0 mt-3">
                    <i class="bi bi-whatsapp"></i>
                    Di ponsel, tombol <strong>Bagikan…</strong> membuka menu berbagi Android/iOS —
                    pilih WhatsApp maka berkas ikut terlampir.
                    Di komputer, berkas akan diunduh lebih dulu lalu lampirkan manual di WhatsApp.
                </p>
            </div>
            <div class="modal-footer flex-wrap gap-2">
                <button type="button" class="btn btn-success" id="ledgerShareNative" disabled>
                    <i class="bi bi-share-fill"></i> Bagikan…
                </button>
                <button type="button" class="btn btn-outline-dark" id="ledgerShareDownload" disabled>
                    <i class="bi bi-download"></i> Unduh
                </button>
                <button type="button" class="btn btn-outline-success" id="ledgerShareWa" disabled>
                    <i class="bi bi-whatsapp"></i> Buka WhatsApp
                </button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_nav_end.php'; ?>

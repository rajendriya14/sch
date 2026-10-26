<?php
/**
 * FFR — Daftar Belanja per bucket.
 *
 * Variabel: $period, $periodLabel, $periodOptions, $buckets, $detail,
 *           $summaries, $catalog, $isClosed
 */
use Helpers\FormHelper;
use Helpers\MoneyHelper;
use Models\FfrItem;

$pageName = $pageName ?? 'ffr';
$ffrActive = 'items';
require __DIR__ . '/_nav.php';

$bucket = $detail['bucket'];
$bucketId = (int) $bucket['id'];
$items = $detail['items'];
$status = $detail['status'];
$planTotal = $detail['plan_total'];
$allocated = $detail['allocated'];
$remaining = $detail['remaining'];
$pct = $allocated > 0 ? min(100, MoneyHelper::percent($planTotal, $allocated, 1)) : ($planTotal > 0 ? 100 : 0);
$barClass = match ($status['key']) {
    'aman' => 'bg-success',
    'ketat' => 'bg-warning',
    'lewat', 'tanpa_dana' => 'bg-danger',
    default => 'bg-secondary',
};

// Overview "Dana Aktual": berapa dana yang benar-benar sudah terealisasi.
$actualTotal = (float) $detail['actual_total'];
$actualPct = $allocated > 0
    ? min(100, MoneyHelper::percent($actualTotal, $allocated, 1))
    : ($actualTotal > 0 ? 100 : 0);
$actualBarClass = match (true) {
    $allocated <= 0 && $actualTotal > 0 => 'bg-danger',
    $actualTotal > $allocated => 'bg-danger',
    $actualPct >= 90 => 'bg-warning',
    $actualTotal > 0 => 'bg-success',
    default => 'bg-secondary',
};

// Catatan singkat di bawah angka Dana Aktual: % dana terpakai + selisih vs rencana.
$actualDiff = $actualTotal - $planTotal;
$actualNote = MoneyHelper::percentText($actualPct) . '% dari dana';
if ($actualTotal > 0 && abs($actualDiff) >= 1) {
    $actualNote .= $actualDiff > 0
        ? ' · lebih ' . MoneyHelper::rupiah($actualDiff) . ' dari rencana'
        : ' · hemat ' . MoneyHelper::rupiah(abs($actualDiff)) . ' dari rencana';
}
?>

<!-- Pemilih bucket -->
<div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach ($summaries as $s): ?>
        <?php $sid = (int) $s['bucket']['id']; ?>
        <a href="index.php?page=ffr&action=items&period=<?= urlencode($period['period']) ?>&bucket=<?= $sid ?>"
           class="btn btn-sm <?= $sid === $bucketId ? 'btn-dark' : 'btn-outline-dark' ?>"
           data-ffr-pill="<?= $sid ?>">
            <?= htmlspecialchars($s['bucket']['name']) ?>
            <span class="badge <?= $s['status']['badge'] ?> ms-1"><?= MoneyHelper::short($s['plan_total']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Toolbar: refresh & hapus isi form tambah item -->
<div class="d-flex flex-wrap align-items-center gap-2 mb-2" id="ffrDraftBar"
     data-draft-key="items:<?= htmlspecialchars($period['period']) ?>:<?= $bucketId ?>">
    <button type="button" class="btn btn-sm btn-outline-dark" id="ffrRefreshBtn"
            title="Muat ulang data terbaru; isian yang belum disimpan tetap dipulihkan">
        <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
    <button type="button" class="btn btn-sm btn-outline-danger" id="ffrClearBtn"
            title="Kosongkan isian di layar; item yang sudah tersimpan tidak berubah">
        <i class="bi bi-eraser"></i> Hapus Isi
    </button>
    <span class="small text-muted d-none" id="ffrDraftState">
        <i class="bi bi-record-circle text-warning"></i> ada isian belum tersimpan
    </span>
    <span class="small text-muted d-none" id="ffrDraftRestored">
        <i class="bi bi-arrow-counterclockwise text-primary"></i> Draft dipulihkan
        — <a href="#" id="ffrDraftDiscard">buang draft</a>
    </span>
</div>

<div class="card mb-3">
    <div class="card-header bg-dark text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>
            <i class="bi bi-list-check"></i>
            <?= htmlspecialchars($bucket['name']) ?>
            <span class="text-white-50 small ms-2"><?= htmlspecialchars((string) ($bucket['description'] ?? '')) ?></span>
        </span>
        <span class="badge <?= $status['badge'] ?>" id="ffrBucketStatus">
            <i class="bi <?= $status['icon'] ?>"></i> <?= $status['label'] ?>
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-2">
            <div class="col-6 col-lg-3">
                <div class="ffr-stat">
                    <span class="text-muted small">Dana dialokasikan</span>
                    <strong id="ffrBucketAllocated"><?= MoneyHelper::rupiah($allocated) ?></strong>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="ffr-stat">
                    <span class="text-muted small">Rencana item</span>
                    <strong id="ffrBucketPlan"><?= MoneyHelper::rupiah($planTotal) ?></strong>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="ffr-stat">
                    <span class="text-muted small">Dana Aktual</span>
                    <strong id="ffrBucketActual" class="<?= $actualTotal > $allocated && $allocated > 0 ? 'text-danger' : '' ?>"
                            title="Total realisasi yang sudah dicatat">
                        <?= MoneyHelper::rupiah($actualTotal) ?>
                    </strong>
                    <small class="d-block text-muted" id="ffrBucketActualNote"><?= $actualNote ?></small>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="ffr-stat">
                    <span class="text-muted small">Sisa dana</span>
                    <strong id="ffrBucketRemaining" class="<?= $remaining < 0 ? 'text-danger' : 'text-success' ?>">
                        <?= $remaining < 0 ? '−' . MoneyHelper::rupiah(abs($remaining)) : MoneyHelper::rupiah($remaining) ?>
                    </strong>
                </div>
            </div>
        </div>
        <div class="progress ffr-progress">
            <div class="progress-bar <?= $barClass ?>" id="ffrBucketBar" style="width: <?= $pct ?>%"></div>
        </div>
        <div class="text-muted small mt-1"><span id="ffrBucketPct"><?= MoneyHelper::percentText($pct) ?></span>% dari dana terpakai oleh rencana item</div>

        <div class="progress ffr-progress mt-2">
            <div class="progress-bar <?= $actualBarClass ?>" id="ffrActualBar" style="width: <?= $actualPct ?>%"></div>
        </div>
        <div class="text-muted small mt-1">
            <span id="ffrActualPct"><?= MoneyHelper::percentText($actualPct) ?></span>% dari dana sudah terealisasi (Dana Aktual)
        </div>

        <?php if ($allocated <= 0 && $planTotal > 0): ?>
            <div class="alert alert-warning py-2 small mt-2 mb-0" id="ffrBucketEmptyAlert">
                <i class="bi bi-exclamation-triangle"></i>
                Kelompok ini belum punya dana, tapi sudah ada item.
                <a href="index.php?page=ffr&action=plan&period=<?= urlencode($period['period']) ?>">Isi alokasinya</a>.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-dark text-white">
        <i class="bi bi-table"></i> Item Belanja — <?= count($items) ?> item
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 ffr-items-table">
                <colgroup>
                    <col class="ffr-col-no">
                    <col class="ffr-col-item">
                    <col class="ffr-col-cat">
                    <col class="ffr-col-freq">
                    <col class="ffr-col-price">
                    <col class="ffr-col-qty">
                    <col class="ffr-col-month">
                    <col class="ffr-col-actual">
                    <col class="ffr-col-status">
                    <col class="ffr-col-action">
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
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                Belum ada item di kelompok <strong><?= htmlspecialchars($bucket['name']) ?></strong>.
                                Tambahkan lewat form di bawah.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $i => $item): ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td>
                                    <span class="ffr-item-name" title="<?= htmlspecialchars($item['name'] . (!empty($item['notes']) ? ' — ' . $item['notes'] : '') . (!empty($item['is_priority']) ? ' · Prioritas' : '')) ?>">
                                        <strong><?= htmlspecialchars($item['name']) ?></strong><?php if (!empty($item['is_priority'])): ?><i class="bi bi-pin-angle-fill text-danger ms-1 ffr-pin"
                                               title="Prioritas — wajib / tidak bisa ditunda"></i><?php endif; ?>
                                    </span>
                                </td>
                                <td><small><?= htmlspecialchars($item['category_name'] ?? '—') ?></small></td>
                                <td>
                                    <small>
                                        <?= htmlspecialchars($item['freq_label']) ?>
                                        <?php if ($item['multiplier'] > 0): ?>
                                            <span class="text-muted">×<?= (int) $item['multiplier'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">(1×)</span>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td class="text-end"><?= MoneyHelper::rupiah($item['unit_price']) ?></td>
                                <td class="text-end"><?= rtrim(rtrim(number_format((float) $item['qty'], 2, ',', '.'), '0'), ',') ?></td>
                                <td class="text-end"><strong><?= MoneyHelper::rupiah($item['monthly_cost']) ?></strong></td>
                                <td class="text-end">
                                    <input type="text" inputmode="numeric"
                                           class="form-control form-control-sm text-end ffr-actual ffr-amount"
                                           data-item="<?= (int) $item['id'] ?>"
                                           value="<?= ($item['actual_amount'] === null || $item['actual_amount'] === '') ? '' : MoneyHelper::rupiah($item['actual_amount'], false) ?>"
                                           placeholder="—"
                                           <?= $isClosed ? 'disabled' : '' ?>>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $item['status']['class'] ?>-subtle text-<?= $item['status']['class'] ?> border border-<?= $item['status']['class'] ?>"
                                          data-ffr-status="<?= (int) $item['id'] ?>">
                                        <i class="bi <?= $item['status']['icon'] ?>"></i> <?= $item['status']['label'] ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary ffr-edit-item"
                                            data-item='<?= htmlspecialchars(json_encode([
                                                'id' => (int) $item['id'],
                                                'name' => $item['name'],
                                                'bucket_id' => (int) $item['bucket_id'],
                                                'category_id' => $item['category_id'],
                                                'freq_type' => $item['freq_type'],
                                                'unit_price' => (int) $item['unit_price'],
                                                'qty' => (float) $item['qty'],
                                                'actual_amount' => $item['actual_amount'] === null ? null : (int) $item['actual_amount'],
                                                'is_priority' => (int) $item['is_priority'],
                                                'notes' => $item['notes'],
                                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'
                                            title="Ubah item">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="index.php?page=ffr&action=deleteItem&id=<?= (int) $item['id'] ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       data-confirm="Hapus item &quot;<?= htmlspecialchars($item['name']) ?>&quot;?">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($items)): ?>
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td colspan="6" class="text-end">Total rencana per bulan</td>
                            <td class="text-end" id="ffrItemsPlanTotal"><?= MoneyHelper::rupiah($planTotal) ?></td>
                            <td class="text-end" id="ffrItemsActualTotal"><?= MoneyHelper::rupiah($detail['actual_total']) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Form tambah / ubah item -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <i class="bi bi-plus-circle"></i> <span id="ffrFormTitle">Tambah Item</span>
    </div>
    <div class="card-body">
        <form method="post" action="index.php?page=ffr&action=saveItem" class="row g-3" id="ffrItemForm"
              data-draft-key="items:<?= htmlspecialchars($period['period']) ?>:<?= $bucketId ?>">
            <?= FormHelper::csrfField() ?>
                        <input type="hidden" name="period" value="<?= htmlspecialchars($period['period']) ?>">
                        <input type="hidden" name="item_id" id="ffrItemId" value="">

            <div class="col-md-4">
                <label class="form-label" for="ffrItemName">Nama Item <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="ffrItemName" name="name" required
                       placeholder="Bensin, Makanan Warung, Odol…" maxlength="100">
            </div>

            <div class="col-md-3">
                <label class="form-label" for="ffrItemCategory">Kategori</label>
                <select class="form-select" id="ffrItemCategory" name="category_id">
                    <option value="">— Tanpa kategori —</option>
                    <?php foreach ($catalog as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-5">
                <label class="form-label" for="ffrItemCategoryNew">Kategori Baru <span class="text-muted small">(kalau belum ada)</span></label>
                <input type="text" class="form-control" id="ffrItemCategoryNew" name="category_new"
                       placeholder="mis. Mandi, Dapur, Rumah" maxlength="50">
            </div>

            <div class="col-md-3">
                <label class="form-label" for="ffrItemFreq">Frekuensi Beli</label>
                <select class="form-select" id="ffrItemFreq" name="freq_type">
                    <?php foreach (FfrItem::FREQ_LABEL as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $key === 'monthly' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?><?= FfrItem::FREQ_MULTIPLIER[$key] > 0 ? ' (×' . FfrItem::FREQ_MULTIPLIER[$key] . '/bulan)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="ffrItemPrice">Harga Satuan</label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="text" inputmode="numeric" class="form-control ffr-amount" id="ffrItemPrice"
                           name="unit_price" value="">
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label" for="ffrItemQty">Jumlah</label>
                <input type="text" inputmode="decimal" class="form-control" id="ffrItemQty" name="qty" value="1">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="ffrItemEstimate">Perkiraan / Bulan</label>
                <div class="form-control bg-light fw-semibold" id="ffrItemEstimate">Rp 0</div>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="ffrItemActual">Aktual Terpakai <span class="text-muted small">(opsional)</span></label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="text" inputmode="numeric" class="form-control ffr-amount" id="ffrItemActual"
                           name="actual_amount" value="">
                </div>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="ffrItemPriority" name="is_priority">
                    <label class="form-check-label" for="ffrItemPriority">
                        Prioritas — jangan ditunda
                    </label>
                </div>
            </div>

            <div class="col-md-8">
                <label class="form-label" for="ffrItemNotes">Catatan</label>
                <input type="text" class="form-control" id="ffrItemNotes" name="notes"
                       placeholder="mis. beli di warung Bu Ani" maxlength="255">
            </div>

            <div class="col-md-2">
                <label class="form-label" for="ffrItemSort">Urutan</label>
                <input type="number" class="form-control" id="ffrItemSort" name="sort_order" value="0">
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <select class="form-select" name="bucket_id" id="ffrItemBucket" aria-label="Kelompok">
                    <?php foreach ($summaries as $s): ?>
                        <option value="<?= (int) $s['bucket']['id'] ?>" <?= (int) $s['bucket']['id'] === $bucketId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['bucket']['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary" <?= $isClosed ? 'disabled' : '' ?>>
                    <i class="bi bi-save"></i> <span id="ffrSubmitLabel">Tambah Item</span>
                </button>
                <button type="button" class="btn btn-secondary d-none" id="ffrCancelEdit">
                    <i class="bi bi-x-circle"></i> Batal Ubah
                </button>
                <?php if ($isClosed): ?>
                    <span class="text-muted small align-self-center">Bulan ditutup — item dikunci.</span>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/_nav_end.php'; ?>

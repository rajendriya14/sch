<?php
/**
 * FFR — Tabunganku: Harga Emas.
 *
 * Menampilkan harga emas harian per brand & pecahan (otomatis + manual),
 * perbandingan antar brand, histori, dan pembaruan massal aset emas.
 *
 * Variabel: $comparison, $bulk, $history, $weight
 */
use Helpers\FormHelper;
use Helpers\MoneyHelper;
use Models\FfrGoldPrice;

$pageName = $pageName ?? 'ffr';
$ffrActive = 'gold_prices';
$ffrSubmenu = 'savings';
require __DIR__ . '/_nav.php';

$rows = $comparison['rows'] ?? [];
$rowsByBrand = [];
foreach ($rows as $row) {
    $rowsByBrand[$row['brand']] = $row;
}

$weightKey = FfrGoldPrice::weightKey((float) $weight);
$spot = $comparison['spot'] ?? null;
$changedBulk = array_values(array_filter($bulk, fn($b) => !empty($b['changed'])));
$gainClass = fn(?float $value): string => $value === null || abs($value) < 0.005 ? 'text-muted' : ($value > 0 ? 'text-success' : 'text-danger');
?>

<!-- Toolbar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" action="index.php" class="d-flex align-items-center gap-2">
        <input type="hidden" name="page" value="ffr">
        <input type="hidden" name="action" value="goldPrices">
        <label for="goldWeight" class="form-label mb-0 small text-muted text-nowrap">Pecahan</label>
        <select class="form-select form-select-sm" id="goldWeight" name="weight"
                onchange="this.form.submit()" style="max-width: 170px;">
            <?php foreach (FfrGoldPrice::WEIGHTS as $w): ?>
                <?php $wKey = FfrGoldPrice::weightKey((float) $w); ?>
                <option value="<?= $wKey ?>" <?= $weightKey === $wKey ? 'selected' : '' ?>>
                    <?= $wKey ?> gram
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <div class="d-flex flex-wrap gap-2">
        <a href="index.php?page=ffr&action=goldPrices&weight=<?= $weightKey ?>&refresh=1"
           class="btn btn-sm btn-dark">
            <i class="bi bi-cloud-arrow-down"></i> Ambil Semua Harga
        </a>
        <a href="index.php?page=ffr&action=savings" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-wallet2"></i> Portofolio
        </a>
    </div>
</div>

<!-- Status sumber -->
<div class="alert <?= !empty($comparison['stale']) ? 'alert-warning' : 'alert-light' ?> border py-2 small mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <span>
            <i class="bi bi-info-circle"></i>
            <?php if (!empty($comparison['date'])): ?>
                Harga per <strong><?= htmlspecialchars(date('d/m/Y', strtotime($comparison['date']))) ?></strong>
                <?php if (!empty($comparison['fetched_at'])): ?>
                    · diambil <?= htmlspecialchars(date('d/m/Y H:i', strtotime($comparison['fetched_at']))) ?> WIB
                <?php endif; ?>
            <?php else: ?>
                Belum ada harga tersimpan.
            <?php endif; ?>
        </span>
        <span>
            <?php if ($spot && !empty($spot['idr_gram'])): ?>
                Spot: <strong><?= MoneyHelper::rupiah($spot['idr_gram']) ?>/gram</strong>
                <?php if (!empty($spot['usd_oz'])): ?>
                    · XAU/USD <strong>$<?= number_format((float) $spot['usd_oz'], 2, ',', '.') ?>/oz</strong>
                <?php endif; ?>
                <?php if (!empty($spot['usd_idr'])): ?>
                    · kurs <strong><?= MoneyHelper::rupiah($spot['usd_idr']) ?></strong>
                <?php endif; ?>
            <?php else: ?>
                Spot belum tersedia.
            <?php endif; ?>
        </span>
    </div>
</div>

<div class="row g-3">
    <!-- Tabel harga -->
    <div class="col-xl-8">
        <form method="post" action="index.php?page=ffr&action=saveGoldPrices">
            <?= FormHelper::csrfField() ?>
            <input type="hidden" name="weight" value="<?= $weightKey ?>">
            <div class="card h-100">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-gem"></i> Harga Emas — <?= $weightKey ?> gram</span>
                    <span class="small">Harga beli &amp; buyback per pecahan</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 150px;">Brand</th>
                                    <th class="text-end" style="min-width: 150px;">Harga Beli (Rp)</th>
                                    <th class="text-end" style="min-width: 150px;">Buyback (Rp)</th>
                                    <th class="text-end" style="min-width: 110px;">/ gram</th>
                                    <th class="text-end" style="min-width: 90px;" title="Selisih buyback terhadap harga beli; makin kecil makin baik">Spread</th>
                                    <th class="text-end" style="min-width: 110px;">Premium</th>
                                    <th style="min-width: 150px;">Sumber</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (FfrGoldPrice::BRANDS as $brand => $meta): ?>
                                    <?php
                                    if ($brand === 'lainnya') {
                                        continue;
                                    }
                                    $row = $rowsByBrand[$brand] ?? null;
                                    $isAuto = $row && empty($row['is_manual']);
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?= htmlspecialchars($meta['class'] === 'purple' ? 'dark' : $meta['class']) ?>-subtle text-dark border">
                                                <i class="bi bi-gem"></i> <?= htmlspecialchars($meta['label']) ?>
                                            </span>
                                            <?php if ($row && !empty($row['tags'])): ?>
                                                <div class="mt-1">
                                                    <?php foreach ($row['tags'] as $tag): ?>
                                                        <?php
                                                        $tagLabel = [
                                                            'termurah' => 'Termurah',
                                                            'buyback-terbaik' => 'Buyback terbaik',
                                                            'spread-tersempit' => 'Spread tersempit',
                                                        ][$tag] ?? $tag;
                                                        ?>
                                                        <span class="badge bg-success-subtle text-success border border-success">
                                                            <?= htmlspecialchars($tagLabel) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <input type="text" inputmode="numeric" class="form-control form-control-sm text-end ffr-amount"
                                                   name="prices[<?= $brand ?>][buy]"
                                                   value="<?= $row ? MoneyHelper::rupiah($row['buy'], false) : '' ?>"
                                                   placeholder="0">
                                        </td>
                                        <td class="text-end">
                                            <input type="text" inputmode="numeric" class="form-control form-control-sm text-end ffr-amount"
                                                   name="prices[<?= $brand ?>][buyback]"
                                                   value="<?= $row && $row['buyback'] !== null ? MoneyHelper::rupiah($row['buyback'], false) : '' ?>"
                                                   placeholder="0">
                                        </td>
                                        <td class="text-end small">
                                            <?php if ($row): ?>
                                                <?= MoneyHelper::rupiah($row['buy_per_gram']) ?>
                                                <?php if ($row['buyback_per_gram'] !== null): ?>
                                                    <div class="text-muted">BB <?= MoneyHelper::rupiah($row['buyback_per_gram']) ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end small <?= $gainClass($row['spread'] ?? null) ?>">
                                            <?= $row && $row['spread'] !== null ? MoneyHelper::percentText($row['spread'], 2) . '%' : '—' ?>
                                        </td>
                                        <td class="text-end small <?= $gainClass($row['premium'] ?? null) ?>">
                                            <?= $row && $row['premium'] !== null ? ($row['premium'] > 0 ? '+' : '') . MoneyHelper::percentText($row['premium'], 2) . '%' : '—' ?>
                                        </td>
                                        <td class="small">
                                            <?php if ($row): ?>
                                                <span class="badge bg-light text-dark border">
                                                    <i class="bi bi-<?= $isAuto ? 'cloud-check' : 'pencil' ?>"></i>
                                                    <?= htmlspecialchars($row['source']) ?>
                                                </span>
                                                <div class="text-muted">
                                                    <?= htmlspecialchars(date('d/m H:i', strtotime((string) $row['fetched_at']))) ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Belum ada</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex flex-wrap justify-content-between gap-2">
                    <span class="small text-muted align-self-center">
                        <i class="bi bi-info-circle"></i>
                        Isi manual bila brand belum tersedia otomatis (Semar, Galeri24, Lotus).
                        Kosongkan kedua kolom lalu simpan untuk kembali ke harga otomatis.
                    </span>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save"></i> Simpan Harga Manual
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Ringkasan & aksi -->
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header bg-dark text-white">
                <span><i class="bi bi-trophy"></i> Ringkasan Hari Ini</span>
            </div>
            <div class="card-body">
                <?php if (empty($rows)): ?>
                    <p class="text-muted small mb-0">
                        Belum ada harga. Klik <strong>Ambil Semua Harga</strong> atau isi manual.
                    </p>
                <?php else: ?>
                    <ul class="sv-legend">
                        <?php foreach ($rows as $row): ?>
                            <li>
                                <span class="sv-legend-dot" style="background: var(--bs-<?= $row['class'] === 'purple' ? 'dark' : $row['class'] ?>)"></span>
                                <span class="sv-legend-label"><?= htmlspecialchars($row['label']) ?></span>
                                <span class="sv-legend-value">
                                    <?= MoneyHelper::short($row['buy_per_gram']) ?>/gr
                                    <small class="text-muted"><?= htmlspecialchars(implode(', ', array_map(fn($t) => [
                                        'termurah' => 'termurah',
                                        'buyback-terbaik' => 'buyback terbaik',
                                        'spread-tersempit' => 'spread tersempit',
                                    ][$t] ?? $t, $row['tags']))) ?: '—' ?></small>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" id="bulk">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-arrow-repeat"></i> Perbarui Aset Emas</span>
                <span class="badge bg-light text-dark"><?= count($changedBulk) ?> berubah</span>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Terapkan harga terbaru ke seluruh aset emas sekaligus. Pratinjau ditampilkan
                    sebelum disimpan.
                </p>

                <?php if (empty($bulk)): ?>
                    <p class="small text-muted mb-0">Belum ada aset emas.</p>
                <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-dark w-100"
                            data-bs-toggle="modal" data-bs-target="#goldBulkModal">
                        <i class="bi bi-eye"></i> Lihat Pratinjau &amp; Terapkan
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Histori -->
<div class="card mt-3">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> Histori Harga — <?= $weightKey ?> gram</span>
        <span class="small">14 hari terakhir</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 110px;">Tanggal</th>
                        <th style="min-width: 140px;">Brand</th>
                        <th class="text-end">Beli</th>
                        <th class="text-end">Buyback</th>
                        <th class="text-end">/ gram</th>
                        <th style="min-width: 130px;">Sumber</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Belum ada histori untuk pecahan ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $item): ?>
                            <?php $itemWeight = (float) $item['weight_gram'] > 0 ? (float) $item['weight_gram'] : 1.0; ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($item['price_date']))) ?></td>
                                <td><?= htmlspecialchars(FfrGoldPrice::brandMeta($item['brand_key'])['label']) ?></td>
                                <td class="text-end"><?= MoneyHelper::rupiah($item['buy_price']) ?></td>
                                <td class="text-end">
                                    <?= $item['buyback_price'] !== null ? MoneyHelper::rupiah($item['buyback_price']) : '—' ?>
                                </td>
                                <td class="text-end small text-muted">
                                    <?= MoneyHelper::rupiah((float) $item['buy_price'] / $itemWeight) ?>
                                </td>
                                <td class="small">
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($item['source']) ?>
                                    </span>
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
    <i class="bi bi-exclamation-triangle"></i>
    Harga otomatis berasal dari sumber publik dan bisa berubah/berhenti kapan saja.
    Angka perbandingan bersifat informatif, bukan saran investasi. Selalu cek harga resmi
    sebelum bertransaksi.
</p>

<?php if (!empty($bulk)): ?>
<!-- Modal pratinjau pembaruan massal -->
<div class="modal fade" id="goldBulkModal" tabindex="-1" aria-labelledby="goldBulkTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="index.php?page=ffr&action=goldBulkApply">
                <?= FormHelper::csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="goldBulkTitle">
                        <i class="bi bi-arrow-repeat"></i> Pratinjau Perubahan Harga Aset Emas
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Harga disimpan per gram. Hanya aset yang dicentang dan berubah yang akan diperbarui.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;">
                                        <input class="form-check-input" type="checkbox" id="goldBulkAll" checked>
                                    </th>
                                    <th>Aset</th>
                                    <th class="text-end">Harga Lama</th>
                                    <th class="text-end">Harga Baru</th>
                                    <th style="min-width: 120px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bulk as $entry): ?>
                                    <tr class="<?= $entry['changed'] ? '' : 'text-muted' ?>">
                                        <td>
                                            <?php if ($entry['changed']): ?>
                                                <input class="form-check-input gold-bulk-item" type="checkbox"
                                                       name="ids[]" value="<?= (int) $entry['id'] ?>"
                                                       id="goldBulk<?= (int) $entry['id'] ?>" checked>
                                            <?php else: ?>
                                                <i class="bi bi-dash"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <label for="goldBulk<?= (int) $entry['id'] ?>" class="mb-0">
                                                <strong><?= htmlspecialchars($entry['name']) ?></strong>
                                            </label>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars($entry['brand_label']) ?>
                                                · <?= FfrGoldPrice::weightKey((float) $entry['weight']) ?> gram
                                            </div>
                                        </td>
                                        <td class="text-end small">
                                            <div>Beli <?= MoneyHelper::rupiah($entry['old_buy']) ?></div>
                                            <div class="text-muted">BB <?= MoneyHelper::rupiah($entry['old_buyback']) ?></div>
                                        </td>
                                        <td class="text-end small">
                                            <?php if ($entry['new_buy'] !== null): ?>
                                                <div>Beli <?= MoneyHelper::rupiah($entry['new_buy']) ?></div>
                                                <div class="text-muted">
                                                    BB <?= $entry['new_buyback'] !== null ? MoneyHelper::rupiah($entry['new_buyback']) : '—' ?>
                                                </div>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td class="small">
                                            <?php if ($entry['changed']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success">
                                                    Diperbarui
                                                </span>
                                                <?php if (!empty($entry['price_date'])): ?>
                                                    <div class="text-muted">
                                                        per <?= htmlspecialchars(date('d/m/Y', strtotime($entry['price_date']))) ?>
                                                        · <?= htmlspecialchars((string) $entry['source']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted"><?= htmlspecialchars($entry['reason'] ?: 'Tanpa perubahan') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary" <?= count($changedBulk) ? '' : 'disabled' ?>>
                        <i class="bi bi-check2"></i> Terapkan ke
                        <?= count($changedBulk) ?> Aset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_nav_end.php'; ?>

<script>
    // Centang/hapus centang semua aset sekaligus di modal pratinjau.
    (function () {
        var all = document.getElementById('goldBulkAll');
        if (!all) return;
        all.addEventListener('change', function () {
            document.querySelectorAll('.gold-bulk-item').forEach(function (item) {
                item.checked = all.checked;
            });
        });
    })();
</script>

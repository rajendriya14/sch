<?php
/**
 * FFR — Tabunganku: Master Kategori.
 *
 * Memilih kategori investasi yang boleh dipakai saat input dan yang ikut
 * ditampilkan di portofolio. Urutan tampil juga diatur di sini.
 *
 * Variabel: $types (key => pengaturan + count), $period, $periodLabel
 */
use Helpers\FormHelper;
use Models\FfrSavingsAsset;

$pageName = $pageName ?? 'ffr';
$ffrActive = 'savings_master';
$ffrSubmenu = 'savings';
require __DIR__ . '/_nav.php';

$totalTypes = count($types);
$formCount = count(array_filter($types, fn($t) => !empty($t['show_in_form'])));
$listCount = count(array_filter($types, fn($t) => !empty($t['show_in_list'])));
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-sliders"></i> Master Kategori Tabunganku</h4>
        <p class="text-muted mb-0 small">
            Pilih kategori yang ingin Anda input dan tampilkan. Kategori yang tidak dipilih
            tetap tersimpan, hanya disembunyikan.
        </p>
    </div>
    <a href="index.php?page=ffr&action=savings" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali ke Tabunganku
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Kategori Aktif untuk Input</div>
                <div class="fs-4 fw-bold"><?= $formCount ?> <span class="fs-6 text-muted">/ <?= $totalTypes ?></span></div>
                <div class="small text-muted">muncul di form tambah aset</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Kategori Ditampilkan</div>
                <div class="fs-4 fw-bold"><?= $listCount ?> <span class="fs-6 text-muted">/ <?= $totalTypes ?></span></div>
                <div class="small text-muted">ikut dihitung di portofolio</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Cara Kerja</div>
                <div class="small">
                    <div><i class="bi bi-check2-square"></i> <strong>Input</strong> — kategori muncul saat menambah aset.</div>
                    <div><i class="bi bi-eye"></i> <strong>Tampilkan</strong> — aset kategori ini masuk tabel &amp; total.</div>
                    <div class="text-muted mt-1">Keduanya bisa dipilih bebas, termasuk salah satu saja.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="post" action="index.php?page=ffr&action=saveSavingsTypes">
    <?= FormHelper::csrfField() ?>
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-check"></i> Daftar Kategori</span>
            <span class="small"><?= $totalTypes ?> kategori</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 64px;">Urutan</th>
                            <th style="min-width: 220px;">Kategori</th>
                            <th class="text-center" style="width: 110px;">
                                Input
                                <i class="bi bi-info-circle text-muted" title="Bisa dipilih di form tambah aset"></i>
                            </th>
                            <th class="text-center" style="width: 120px;">
                                Tampilkan
                                <i class="bi bi-info-circle text-muted" title="Ikut dihitung & tampil di portofolio"></i>
                            </th>
                            <th class="text-end" style="width: 110px;">Aset</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($types as $key => $type): ?>
                            <?php $meta = FfrSavingsAsset::typeMeta($key); ?>
                            <tr>
                                <td>
                                    <input type="number" class="form-control form-control-sm"
                                           name="types[<?= htmlspecialchars($key) ?>][sort_order]"
                                           value="<?= (int) $type['sort_order'] ?>" min="0" step="1"
                                           aria-label="Urutan <?= htmlspecialchars($meta['label']) ?>">
                                </td>
                                <td>
                                    <i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i>
                                    <strong><?= htmlspecialchars($meta['label']) ?></strong>
                                    <small class="text-muted d-block"><?= htmlspecialchars($key) ?></small>
                                </td>
                                <td class="text-center">
                                    <input class="form-check-input" type="checkbox"
                                           id="svTypeForm_<?= $key ?>"
                                           name="types[<?= htmlspecialchars($key) ?>][show_in_form]"
                                           value="1" <?= !empty($type['show_in_form']) ? 'checked' : '' ?>>
                                    <label class="visually-hidden" for="svTypeForm_<?= $key ?>">Input <?= htmlspecialchars($meta['label']) ?></label>
                                </td>
                                <td class="text-center">
                                    <input class="form-check-input" type="checkbox"
                                           id="svTypeList_<?= $key ?>"
                                           name="types[<?= htmlspecialchars($key) ?>][show_in_list]"
                                           value="1" <?= !empty($type['show_in_list']) ? 'checked' : '' ?>>
                                    <label class="visually-hidden" for="svTypeList_<?= $key ?>">Tampilkan <?= htmlspecialchars($meta['label']) ?></label>
                                </td>
                                <td class="text-end">
                                    <?php $assetCount = (int) ($type['count'] ?? 0); ?>
                                    <?php if ($assetCount > 0): ?>
                                        <a href="index.php?page=ffr&action=savings" class="badge bg-light text-dark border text-decoration-none">
                                            <?= $assetCount ?> aset
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between gap-2">
            <div class="small text-muted align-self-center">
                <i class="bi bi-exclamation-triangle"></i>
                Menyembunyikan kategori tidak menghapus asetnya.
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Simpan Pengaturan
                </button>
            </div>
        </div>
    </div>
</form>

<form method="post" action="index.php?page=ffr&action=resetSavingsTypes" class="mt-2">
    <?= FormHelper::csrfField() ?>
    <button type="submit" class="btn btn-sm btn-outline-secondary"
            data-confirm="Aktifkan kembali semua kategori Tabunganku?">
        <i class="bi bi-arrow-counterclockwise"></i> Aktifkan Semua Kategori
    </button>
</form>

<?php require __DIR__ . '/_nav_end.php'; ?>

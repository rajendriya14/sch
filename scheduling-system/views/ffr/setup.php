<?php
/**
 * FFR — Master: kelompok dana (bucket) + kategori.
 *
 * Variabel: $buckets, $categories
 */
use Helpers\FormHelper;

$pageName = $pageName ?? 'ffr';
$ffrActive = 'setup';
require __DIR__ . '/_nav.php';

$typeLabel = ['wajib' => 'Wajib', 'opsional' => 'Opsional', 'simpan' => 'Simpanan'];
?>

<div class="row g-3">
    <!-- KELOMPOK DANA -->
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-diagram-3"></i> Kelompok Dana (Bucket)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 110px;">Kode</th>
                                <th>Nama</th>
                                <th style="width: 110px;">Tipe</th>
                                <th style="width: 70px;">Urutan</th>
                                <th style="width: 90px;">Aktif</th>
                                <th style="width: 80px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buckets as $bucket): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($bucket['code']) ?></code></td>
                                    <td>
                                        <form method="post" action="index.php?page=ffr&action=updateBucket"
                                              class="d-flex gap-1 align-items-center" id="bucketForm<?= (int) $bucket['id'] ?>">
                                            <?= FormHelper::csrfField() ?>
                                            <input type="hidden" name="id" value="<?= (int) $bucket['id'] ?>">
                                            <input type="text" class="form-control form-control-sm" name="name"
                                                   value="<?= htmlspecialchars($bucket['name']) ?>" maxlength="50">
                                            <input type="hidden" name="sort_order" value="<?= (int) $bucket['sort_order'] ?>">
                                            <input type="hidden" name="description" value="<?= htmlspecialchars((string) $bucket['description']) ?>">
                                        </form>
                                        <?php if (!empty($bucket['description'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars($bucket['description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="type" form="bucketForm<?= (int) $bucket['id'] ?>">
                                            <?php foreach ($typeLabel as $key => $label): ?>
                                                <option value="<?= $key ?>" <?= $bucket['type'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="text-center text-muted"><?= (int) $bucket['sort_order'] ?></td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input" name="is_active" value="1"
                                               form="bucketForm<?= (int) $bucket['id'] ?>"
                                               <?= !empty($bucket['is_active']) ? 'checked' : '' ?>>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <button type="submit" class="btn btn-sm btn-outline-primary"
                                                form="bucketForm<?= (int) $bucket['id'] ?>" title="Simpan">
                                            <i class="bi bi-save"></i>
                                        </button>
                                        <a href="index.php?page=ffr&action=deleteBucket&id=<?= (int) $bucket['id'] ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           data-confirm="Hapus kelompok &quot;<?= htmlspecialchars($bucket['name']) ?>&quot;?">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-plus-circle"></i> Tambah Kelompok Dana
            </div>
            <div class="card-body">
                <form method="post" action="index.php?page=ffr&action=setup" class="row g-2">
                    <?= FormHelper::csrfField() ?>
                    <input type="hidden" name="kind" value="bucket">

                    <div class="col-md-3">
                        <label class="form-label small" for="newBucketCode">Kode</label>
                        <input type="text" class="form-control form-control-sm" id="newBucketCode" name="code"
                               placeholder="HIBURAN" maxlength="20" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="newBucketName">Nama</label>
                        <input type="text" class="form-control form-control-sm" id="newBucketName" name="name"
                               placeholder="Hiburan" maxlength="50" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="newBucketType">Tipe</label>
                        <select class="form-select form-select-sm" id="newBucketType" name="type">
                            <?php foreach ($typeLabel as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="newBucketSort">Urutan</label>
                        <input type="number" class="form-control form-control-sm" id="newBucketSort" name="sort_order"
                               value="<?= count($buckets) + 1 ?>">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-sm btn-primary w-100" title="Tambah">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                    <div class="col-12">
                        <input type="text" class="form-control form-control-sm" name="description"
                               placeholder="Keterangan (opsional)" maxlength="255">
                    </div>
                </form>
                <p class="text-muted small mb-0 mt-2">
                    <i class="bi bi-info-circle"></i>
                    <strong>Tipe</strong> menentukan perlakuan sistem:
                    <em>Opsional</em> = itemnya didahulukan untuk ditunda saat dana kurang;
                    <em>Simpanan</em> = dihitung sebagai tabungan, bukan pengeluaran.
                </p>
            </div>
        </div>
    </div>

    <!-- KATEGORI -->
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-tags"></i> Kategori
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                <th style="width: 90px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-3">
                                        Belum ada kategori. Tambahkan di bawah, atau langsung ketik di form item belanja.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td>
                                            <form method="post" action="index.php?page=ffr&action=updateCategory"
                                                  class="d-flex gap-1" id="catForm<?= (int) $cat['id'] ?>">
                                                <?= FormHelper::csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                                <input type="text" class="form-control form-control-sm" name="name"
                                                       value="<?= htmlspecialchars($cat['name']) ?>" maxlength="50">
                                            </form>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <button type="submit" class="btn btn-sm btn-outline-primary"
                                                    form="catForm<?= (int) $cat['id'] ?>" title="Simpan">
                                                <i class="bi bi-save"></i>
                                            </button>
                                            <a href="index.php?page=ffr&action=deleteCategory&id=<?= (int) $cat['id'] ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               data-confirm="Hapus kategori &quot;<?= htmlspecialchars($cat['name']) ?>&quot;? Item yang memakainya jadi tanpa kategori.">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-plus-circle"></i> Tambah Kategori
            </div>
            <div class="card-body">
                <form method="post" action="index.php?page=ffr&action=setup" class="d-flex gap-2">
                    <?= FormHelper::csrfField() ?>
                    <input type="hidden" name="kind" value="category">
                    <input type="text" class="form-control form-control-sm" name="name"
                           placeholder="mis. Makanan, Transport, Mandi, Dapur" maxlength="50" required>
                    <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                        <i class="bi bi-plus"></i> Tambah
                    </button>
                </form>
                <p class="text-muted small mb-0 mt-2">
                    Kategori dipakai untuk melihat naik/turun per jenis belanja di Dashboard.
                </p>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_nav_end.php'; ?>

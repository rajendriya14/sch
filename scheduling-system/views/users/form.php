<?php
/**
 * Form tambah / ubah user + hak akses menu.
 *
 * Variabel: $user (null saat tambah), $permissions, $groups, $roles
 */
use Config\MenuCatalog;
use Helpers\FormHelper;

$pageName = $pageName ?? 'users';
$isEdit = !empty($user);
$action = $isEdit ? 'update' : 'store';
$isAdminRole = ($user['role'] ?? 'user') === 'admin';
$totalAssignable = $groups ? count(array_merge(...array_values($groups))) : 0;
?>

<div class="row mb-3">
    <div class="col">
        <h2><i class="bi <?= $isEdit ? 'bi-pencil-square' : 'bi-person-plus' ?>"></i>
            <?= $isEdit ? 'Ubah User' : 'Tambah User' ?>
        </h2>
        <?php if ($isEdit): ?>
            <p class="text-muted mb-0">
                Username <strong><?= htmlspecialchars($user['username']) ?></strong> tidak bisa diubah.
            </p>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="index.php?page=users&action=<?= $action ?>">
    <?= FormHelper::csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
    <?php endif; ?>

    <div class="row g-3">
        <!-- DATA AKUN -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">
                    <i class="bi bi-person-badge"></i> Data Akun
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <?php if ($isEdit): ?>
                            <input type="text" class="form-control" id="username"
                                   value="<?= htmlspecialchars($user['username']) ?>" disabled>
                        <?php else: ?>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= htmlspecialchars((string) ($_POST['username'] ?? '')) ?>"
                                   placeholder="mis. budisantoso" maxlength="50" required
                                   pattern="[A-Za-z0-9._\-]{3,50}">
                            <div class="form-text">3–50 karakter: huruf, angka, titik, garis bawah, strip.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="display_name" class="form-label">Nama Tampilan</label>
                        <input type="text" class="form-control" id="display_name" name="display_name"
                               value="<?= htmlspecialchars((string) ($user['display_name'] ?? ($_POST['display_name'] ?? ''))) ?>"
                               placeholder="mis. Budi Santoso" maxlength="100">
                        <div class="form-text">Muncul di pojok kanan atas setelah login.</div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">
                            Password <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?>
                        </label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="password" name="password"
                                   <?= $isEdit ? '' : 'required' ?> autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary" id="genPassword" title="Buat password acak">
                                <i class="bi bi-shuffle"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            <?php if ($isEdit): ?>
                                Kosongkan bila password tidak diubah. Ketik password baru untuk menggantinya.
                            <?php else: ?>
                                Minimal <?= \Models\User::MIN_PASSWORD ?> karakter.
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role">
                                <?php foreach ($roles as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= ($user['role'] ?? 'user') === $key ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active"
                                       <?= (!$isEdit || (int) $user['is_active'] === 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">Akun aktif</label>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-secondary small mt-3 mb-0">
                        <i class="bi bi-info-circle"></i>
                        Akun nonaktif tidak bisa login, tapi datanya tetap tersimpan.
                    </div>
                </div>
            </div>
        </div>

        <!-- HAK AKSES -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-dark text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="bi bi-shield-lock"></i> Hak Akses Menu</span>
                    <span class="badge bg-light text-dark" id="ffrPermCount">0 / <?= (int) $totalAssignable ?> dipilih</span>
                </div>
                <div class="card-body">
                    <div class="alert alert-secondary small py-2" id="adminNote"
                         style="<?= $isAdminRole ? '' : 'display:none' ?>">
                        <i class="bi bi-shield-check"></i>
                        Role <strong>Administrator</strong> otomatis bisa membuka semua menu — centang di bawah diabaikan.
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-dark" id="checkAll">Pilih semua</button>
                        <button type="button" class="btn btn-sm btn-outline-dark" id="uncheckAll">Kosongkan</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="defaultPerm">Izin bawaan</button>
                    </div>

                    <?php foreach ($groups as $groupName => $items): ?>
                        <div class="ffr-perm-group">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="small text-uppercase text-muted"><?= htmlspecialchars($groupName) ?></strong>
                                <button type="button" class="btn btn-sm btn-link p-0 perm-group-toggle"
                                        data-group="<?= htmlspecialchars(md5($groupName)) ?>">
                                    pilih grup ini
                                </button>
                            </div>
                            <div class="row g-1" data-group-box="<?= htmlspecialchars(md5($groupName)) ?>">
                                <?php foreach ($items as $key => $menu): ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input perm-check" type="checkbox"
                                                   name="menus[]" value="<?= htmlspecialchars($key) ?>"
                                                   id="menu_<?= htmlspecialchars($key) ?>"
                                                   <?= in_array($key, $permissions, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="menu_<?= htmlspecialchars($key) ?>">
                                                <i class="bi <?= htmlspecialchars($menu['icon']) ?>"></i>
                                                <?= htmlspecialchars($menu['label']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="alert alert-info small mb-0 mt-2">
                        <i class="bi bi-grid-1x2"></i>
                        <strong>Portal</strong> tidak perlu dicentang — selalu bisa dibuka semua user
                        (halaman pendarat setelah login).
                    </div>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan User' ?>
                    </button>
                    <a href="index.php?page=users" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                    <a href="index.php?page=portal" class="btn btn-outline-secondary ms-auto">
                        <i class="bi bi-grid-1x2"></i> Portal
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    'use strict';

    var checks = document.querySelectorAll('.perm-check');
    var counter = document.getElementById('ffrPermCount');
    var total = <?= (int) $totalAssignable ?>;
    var defaults = <?= json_encode(MenuCatalog::defaultUserPermissions()) ?>;
    var roleSelect = document.getElementById('role');
    var adminNote = document.getElementById('adminNote');

    function updateCount() {
        var n = 0;
        checks.forEach(function (c) { if (c.checked) n++; });
        if (counter) counter.textContent = n + ' / ' + total + ' dipilih';
    }

    checks.forEach(function (c) { c.addEventListener('change', updateCount); });
    updateCount();

    document.getElementById('checkAll').addEventListener('click', function () {
        checks.forEach(function (c) { c.checked = true; });
        updateCount();
    });

    document.getElementById('uncheckAll').addEventListener('click', function () {
        checks.forEach(function (c) { c.checked = false; });
        updateCount();
    });

    document.getElementById('defaultPerm').addEventListener('click', function () {
        checks.forEach(function (c) { c.checked = defaults.indexOf(c.value) !== -1; });
        updateCount();
    });

    document.querySelectorAll('.perm-group-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = document.querySelector('[data-group-box="' + this.dataset.group + '"]');
            if (!box) return;
            var items = box.querySelectorAll('.perm-check');
            var allOn = Array.prototype.every.call(items, function (i) { return i.checked; });
            items.forEach(function (i) { i.checked = !allOn; });
            updateCount();
        });
    });

    function syncRoleNote() {
        if (adminNote) adminNote.style.display = roleSelect.value === 'admin' ? '' : 'none';
    }
    roleSelect.addEventListener('change', syncRoleNote);
    syncRoleNote();

    document.getElementById('genPassword').addEventListener('click', function () {
        var chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        var out = '';
        for (var i = 0; i < 8; i++) out += chars.charAt(Math.floor(Math.random() * chars.length));
        document.getElementById('password').value = out;
    });
})();
</script>

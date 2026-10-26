<?php
/**
 * Form ganti password untuk user yang sedang login.
 */
$error = $error ?? '';
$currentUser = \Services\AuthService::user();
?>

<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-key"></i> Ganti Password</h2>
        <p class="text-muted mb-0">
            Login sebagai <strong><?= htmlspecialchars($currentUser['name'] ?? '') ?></strong>
            (<?= htmlspecialchars($currentUser['username'] ?? '') ?>).
        </p>
    </div>
</div>

<div class="card" style="max-width: 560px;">
    <div class="card-body">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="index.php?page=auth&action=changePassword" class="row g-3">
            <?= \Helpers\FormHelper::csrfField() ?>

            <div class="col-12">
                <label for="current_password" class="form-label">Password Saat Ini <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
            </div>

            <div class="col-md-6">
                <label for="new_password" class="form-label">Password Baru <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="new_password" name="new_password" required autocomplete="new-password">
            </div>

            <div class="col-md-6">
                <label for="confirm_password" class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Simpan Password
                </button>
                <a href="index.php?page=dashboard" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

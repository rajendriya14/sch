<?php
/**
 * Manajemen User — daftar akun + ringkasan hak akses.
 *
 * Variabel: $users, $groups, $roles, $currentUserId, $adminCount
 */
use Config\MenuCatalog;
use Helpers\MoneyHelper;

$pageName = $pageName ?? 'users';

$formatTime = function (?string $ts): string {
    if (empty($ts)) {
        return '—';
    }
    $t = strtotime($ts);
    return $t ? date('d M Y H:i', $t) : '—';
};
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-0"><i class="bi bi-people-fill"></i> Manajemen User</h2>
        <p class="text-muted mb-0">
            Atur siapa saja yang boleh masuk, dan menu apa saja yang bisa dibuka.
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="index.php?page=portal" class="btn btn-outline-secondary">
            <i class="bi bi-grid-1x2"></i> Portal
        </a>
        <a href="index.php?page=users&action=create" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> Tambah User
        </a>
    </div></div>

<div class="alert alert-info d-flex gap-2 py-2">
    <i class="bi bi-shield-check fs-5"></i>
    <div class="small">
        <strong>Administrator</strong> otomatis punya akses ke <em>semua</em> menu (termasuk halaman ini) —
        daftar centang menu hanya berlaku untuk role <strong>User</strong>.
        Halaman Portal selalu bisa dibuka oleh semua user yang sudah login.
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark text-white">
        <i class="bi bi-list-ul"></i> Terdaftar (<?= count($users) ?> akun, <?= (int) $adminCount ?> administrator aktif)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Nama</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Hak Akses Menu</th>
                        <th>Login Terakhir</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada user.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <?php
                                $isAdmin = $u['role'] === 'admin';
                                $isSelf = (int) $u['id'] === $currentUserId;
                                $isLastAdmin = $isAdmin && (int) $adminCount <= 1;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($u['username']) ?></strong>
                                    <?php if ($isSelf): ?>
                                        <span class="badge bg-secondary ms-1">Anda</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($u['display_name'] ?? '—') ?></td>
                                <td>
                                    <span class="badge <?= $isAdmin ? 'bg-dark' : 'bg-light text-dark border' ?>">
                                        <i class="bi <?= $isAdmin ? 'bi-shield-check' : 'bi-person' ?>"></i>
                                        <?= htmlspecialchars($roles[$u['role']] ?? $u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ((int) $u['is_active'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isAdmin): ?>
                                        <span class="text-muted small">Semua menu (administrator)</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary">
                                            <?= (int) $u['permission_count'] ?> / <?= count($groups ? array_merge(...array_values($groups)) : []) ?> menu
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($formatTime($u['last_login_at'] ?? null)) ?></small></td>
                                <td class="text-end text-nowrap">
                                    <a href="index.php?page=users&action=edit&id=<?= (int) $u['id'] ?>"
                                       class="btn btn-sm btn-outline-primary" title="Ubah user & hak akses">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($isSelf): ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled title="Tidak bisa hapus akun sendiri">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php elseif ($isLastAdmin): ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled title="Administrator aktif terakhir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <a href="index.php?page=users&action=delete&id=<?= (int) $u['id'] ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           data-confirm="Hapus user &quot;<?= htmlspecialchars($u['username']) ?>&quot;? Tindakan ini tidak bisa dibatalkan.">
                                            <i class="bi bi-trash"></i>
                                        </a>
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

<div class="card mt-3">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-grid-3x3-gap"></i> Menu yang Bisa Diatur
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($groups as $groupName => $items): ?>
                <div class="col-md-4">
                    <div class="small text-muted mb-1"><?= htmlspecialchars($groupName) ?></div>
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach ($items as $key => $menu): ?>
                            <li>
                                <i class="bi <?= htmlspecialchars($menu['icon']) ?>"></i>
                                <?= htmlspecialchars($menu['label']) ?>
                                <code class="text-muted"><?= htmlspecialchars($key) ?></code>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

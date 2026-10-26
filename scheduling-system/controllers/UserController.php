<?php
/**
 * Manajemen User — khusus administrator.
 *
 * Admin dapat menambah user, mengatur role, dan memilih menu mana saja
 * yang boleh dibuka oleh user tersebut.
 */
namespace Controllers;

use Config\MenuCatalog;
use Helpers\FormHelper;
use Models\User;
use Services\AccessService;
use Services\AuthService;

class UserController
{
    /** Izin yang boleh dicentang (Portal dikecualikan — selalu terbuka). */
    private array $assignableKeys;

    public function __construct()
    {
        $this->assignableKeys = array_keys(MenuCatalog::assignable());

        // Halaman ini khusus administrator, apa pun izin menunya.
        if (!AccessService::isAdmin(AuthService::user())) {
            FormHelper::setFlash('Halaman Manajemen User hanya untuk administrator.', 'danger');
            header('Location: index.php?page=portal');
            exit;
        }
    }

    /**
     * Render halaman Manajemen User.
     *
     * Halaman ini tampil mandiri seperti FFR: tanpa sidebar Workforce, dengan
     * nama modul di navbar, dan brand diarahkan ke Portal sebagai jalan keluar.
     */
    private function render(string $view, array $vars = []): void
    {
        $hideSidebar  = true;
        $hideAppLabel = true;
        $brandTitle   = 'Manajemen User';
        $brandIcon    = 'bi-gear';
        $brandUrl     = 'index.php?page=portal';
        $pageName     = 'users';

        extract($vars, EXTR_SKIP);

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/users/' . $view . '.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Daftar user + form tambah.
     */
    public function index(): void
    {
        $users = User::all();
        $groups = MenuCatalog::assignableGroups();
        $roles = User::ROLES;
        $currentUserId = (int) (AuthService::user()['id'] ?? 0);
        $adminCount = User::countAdmins();

        $this->render('index', compact('users', 'groups', 'roles', 'currentUserId', 'adminCount'));
    }

    /**
     * Form tambah user.
     */
    public function create(): void
    {
        $user = null;
        $permissions = MenuCatalog::defaultUserPermissions();
        $groups = MenuCatalog::assignableGroups();
        $roles = User::ROLES;

        $this->render('form', compact('user', 'permissions', 'groups', 'roles'));
    }

    /**
     * Simpan user baru.
     */
    public function store(): void
    {
        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect('index.php?page=users&action=create');
        }

        // User baru default aktif kecuali centangnya dilepas.
        $result = User::create([
            'username'     => $_POST['username'] ?? '',
            'password'     => $_POST['password'] ?? '',
            'display_name' => $_POST['display_name'] ?? '',
            'role'         => $_POST['role'] ?? 'user',
            'is_active'    => isset($_POST['is_active']) ? 1 : 0,
        ]);

        if (!$result['success']) {
            FormHelper::setFlash($result['error'], 'danger');
            $this->redirect('index.php?page=users&action=create');
        }

        $this->savePermissions($result['id']);
        AccessService::forget($result['id']);

        FormHelper::setFlash('User "' . trim((string) ($_POST['username'] ?? '')) . '" berhasil ditambahkan.', 'success');
        $this->redirect('index.php?page=users');
    }

    /**
     * Form ubah user.
     */
    public function edit(): void
    {
        $user = User::find((int) ($_GET['id'] ?? 0));
        if (!$user) {
            FormHelper::setFlash('User tidak ditemukan.', 'warning');
            $this->redirect('index.php?page=users');
        }

        $permissions = User::getPermissions((int) $user['id']);
        $groups = MenuCatalog::assignableGroups();
        $roles = User::ROLES;

        $this->render('form', compact('user', 'permissions', 'groups', 'roles'));
    }

    /**
     * Simpan perubahan user.
     */
    public function update(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $user = User::find($id);

        if (!$user || !FormHelper::verifyCsrf()) {
            FormHelper::setFlash($user ? 'Sesi form kedaluwarsa.' : 'User tidak ditemukan.', 'danger');
            $this->redirect('index.php?page=users');
        }

        // Diri sendiri tidak boleh menonaktifkan akunnya sendiri.
        $isSelf = (int) (AuthService::user()['id'] ?? 0) === $id;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($isSelf && $isActive === 0) {
            FormHelper::setFlash('Tidak bisa menonaktifkan akun yang sedang Anda pakai.', 'warning');
            $this->redirect('index.php?page=users&action=edit&id=' . $id);
        }

        $result = User::update($id, [
            'password'     => $_POST['password'] ?? '',
            'display_name' => $_POST['display_name'] ?? '',
            'role'         => $_POST['role'] ?? $user['role'],
            'is_active'    => $isActive,
        ]);

        if (!$result['success']) {
            FormHelper::setFlash($result['error'], 'danger');
            $this->redirect('index.php?page=users&action=edit&id=' . $id);
        }

        $this->savePermissions($id, false);
        AccessService::forget($id);

        FormHelper::setFlash('User "' . $user['username'] . '" diperbarui.', 'success');
        $this->redirect('index.php?page=users');
    }

    /**
     * Hapus user.
     */
    public function delete(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id === (int) (AuthService::user()['id'] ?? 0)) {
            FormHelper::setFlash('Tidak bisa menghapus akun yang sedang Anda pakai.', 'warning');
            $this->redirect('index.php?page=users');
        }

        $result = User::delete($id);
        AccessService::forget($id);

        FormHelper::setFlash(
            $result['success'] ? 'User dihapus.' : $result['error'],
            $result['success'] ? 'success' : 'danger'
        );
        $this->redirect('index.php?page=users');
    }

    /**
     * Simpan centang menu dari form.
     */
    private function savePermissions(int $userId, bool $useDefaults = false): void
    {
        $posted = $_POST['menus'] ?? null;
        $keys = is_array($posted) ? $posted : ($useDefaults ? MenuCatalog::defaultUserPermissions() : []);

        User::setPermissions($userId, $keys, $this->assignableKeys);
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

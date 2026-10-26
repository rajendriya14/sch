<?php
// Controller halaman login / logout / ganti password.
namespace Controllers;

use Helpers\FormHelper;
use Services\AuthService;

class AuthController
{
    private AuthService $service;

    public function __construct()
    {
        $this->service = new AuthService();
    }

    /**
     * Default action: arahkan ke portal (atau halaman login bila belum login).
     */
    public function index(): void
    {
        $this->redirect(AuthService::check() ? 'index.php?page=portal' : 'index.php?page=auth&action=login');
    }

    /**
     * Form + proses login
     */
    public function login(): void
    {
        if (AuthService::check()) {
            $this->redirect('index.php?page=portal');
        }

        $error = '';
        $username = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!FormHelper::verifyCsrf()) {
                $error = 'Sesi form kedaluwarsa. Silakan coba lagi.';
            } else {
                $username = trim($_POST['username'] ?? '');
                $result = $this->service->login($username, $_POST['password'] ?? '');

                if ($result['success']) {
                    FormHelper::setFlash('Selamat datang, ' . (AuthService::user()['name'] ?? $username) . '!', 'success');
                    $this->redirect($this->consumeRedirectTarget());
                }

                $error = $result['error'];
            }
        }

        require_once __DIR__ . '/../views/auth/login.php';
    }

    /**
     * Logout dan kembali ke halaman login
     */
    public function logout(): void
    {
        $this->service->logout();
        FormHelper::setFlash('Anda telah logout.', 'info');
        $this->redirect('index.php?page=auth&action=login');
    }

    /**
     * Form + proses ganti password
     */
    public function changePassword(): void
    {
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!FormHelper::verifyCsrf()) {
                $error = 'Sesi form kedaluwarsa. Silakan coba lagi.';
            } else {
                $result = $this->service->changePassword(
                    $_POST['current_password'] ?? '',
                    $_POST['new_password'] ?? '',
                    $_POST['confirm_password'] ?? ''
                );

                if ($result['success']) {
                    FormHelper::setFlash('Password berhasil diperbarui.', 'success');
                    $this->redirect('index.php?page=dashboard');
                }

                $error = $result['error'];
            }
        }

        $pageName = 'auth';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/auth/change_password.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Ambil halaman yang tadi diminta sebelum diarahkan ke login,
     * hanya bila berupa query internal (page=...) untuk mencegah open redirect.
     */
    private function consumeRedirectTarget(): string
    {
        $target = $_SESSION['auth_redirect'] ?? null;
        unset($_SESSION['auth_redirect']);

        if (is_string($target) && preg_match('/^page=[a-zA-Z0-9_]+(&[a-zA-Z0-9_=\-\[\]%]*)*$/', $target)) {
            return 'index.php?' . $target;
        }

        return 'index.php?page=portal';
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

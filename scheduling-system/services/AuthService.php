<?php
// Autentikasi berbasis session: login, logout, dan ganti password.
namespace Services;

use Models\User;

class AuthService
{
    /** Batas percobaan login gagal sebelum ditahan sementara. */
    private const MAX_ATTEMPTS  = 5;
    private const LOCK_SECONDS  = 60;
    private const MIN_PASSWORD  = 3;

    /**
     * Sudah login atau belum — dipakai front controller & navbar.
     */
    public static function check(): bool
    {
        return !empty($_SESSION['auth_user']['id']);
    }

    /**
     * Data user yang sedang login (id, username, name), atau null.
     */
    public static function user(): ?array
    {
        return $_SESSION['auth_user'] ?? null;
    }

    /**
     * Proses login. Mengembalikan ['success' => bool, 'error' => string].
     */
    public function login(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return ['success' => false, 'error' => 'Username dan password wajib diisi.'];
        }

        $wait = $this->lockRemainingSeconds();
        if ($wait > 0) {
            return ['success' => false, 'error' => 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . $wait . ' detik.'];
        }

        $user = User::verifyCredentials($username, $password);
        if (!$user) {
            $this->registerFailedAttempt();
            return ['success' => false, 'error' => 'Username atau password salah.'];
        }

        unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
        session_regenerate_id(true);

        $_SESSION['auth_user'] = [
            'id'       => (int) $user['id'],
            'username' => $user['username'],
            'name'     => $user['display_name'] ?: $user['username'],
            'role'     => $user['role'] ?? 'user',
        ];

        User::recordLogin((int) $user['id']);

        return ['success' => true, 'error' => ''];
    }

    public function logout(): void
    {
        unset($_SESSION['auth_user'], $_SESSION['login_attempts'], $_SESSION['login_locked_until']);
        session_regenerate_id(true);
    }

    /**
     * Ganti password user yang sedang login.
     */
    public function changePassword(string $current, string $new, string $confirm): array
    {
        $user = self::user();
        if (!$user) {
            return ['success' => false, 'error' => 'Sesi login sudah berakhir. Silakan login ulang.'];
        }

        if ($current === '') {
            return ['success' => false, 'error' => 'Password saat ini wajib diisi.'];
        }
        if ($new !== $confirm) {
            return ['success' => false, 'error' => 'Konfirmasi password tidak sama.'];
        }
        if (strlen($new) < self::MIN_PASSWORD) {
            return ['success' => false, 'error' => 'Password baru minimal ' . self::MIN_PASSWORD . ' karakter.'];
        }

        $row = User::findByUsername($user['username']);
        if (!$row || !password_verify($current, $row['password_hash'])) {
            return ['success' => false, 'error' => 'Password saat ini salah.'];
        }

        User::updatePassword((int) $row['id'], $new);

        return ['success' => true, 'error' => ''];
    }

    private function lockRemainingSeconds(): int
    {
        $until = (int) ($_SESSION['login_locked_until'] ?? 0);
        return max(0, $until - time());
    }

    private function registerFailedAttempt(): void
    {
        $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= self::MAX_ATTEMPTS) {
            $_SESSION['login_locked_until'] = time() + self::LOCK_SECONDS;
            $_SESSION['login_attempts'] = 0;
        }
    }
}

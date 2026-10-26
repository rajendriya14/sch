<?php
/**
 * Hak akses: role admin + izin menu per user.
 *
 * - User dengan role 'admin' otomatis boleh membuka semua menu.
 * - User biasa hanya boleh membuka menu yang tercatat di user_permissions.
 * - 'users' (Manajemen User) selalu khusus admin.
 */
namespace Services;

use Config\Database;
use Config\MenuCatalog;
use Models\User;
use PDO;

class AccessService
{
    /** Cache per-request supaya tidak query berulang. */
    private static array $recordCache = [];
    private static array $permissionCache = [];

    public static function isAdmin(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        $record = self::record((int) ($user['id'] ?? 0));
        return ($record['role'] ?? 'user') === 'admin';
    }

    /**
     * Boleh membuka menu ini?
     */
    public static function can(?array $user, string $menuKey): bool
    {
        if (!$user) {
            return false;
        }

        // Menu yang selalu terbuka untuk semua user yang sudah login.
        if (MenuCatalog::isAlways($menuKey)) {
            return true;
        }

        // Manajemen User khusus admin.
        if ($menuKey === 'users') {
            return self::isAdmin($user);
        }

        if (self::isAdmin($user)) {
            return true;
        }

        if (!MenuCatalog::exists($menuKey)) {
            return false;
        }

        return in_array($menuKey, self::permissions((int) $user['id']), true);
    }

    /**
     * Daftar menu yang boleh dibuka user (admin = semua).
     */
    public static function allowedKeys(?array $user): array
    {
        if (!$user) {
            return [];
        }
        if (self::isAdmin($user)) {
            return array_merge(MenuCatalog::keys(), ['users']);
        }

        return array_values(array_filter(
            MenuCatalog::keys(),
            fn(string $key) => self::can($user, $key)
        ));
    }

    /**
     * Izin tersimpan milik satu user.
     */
    public static function permissions(int $userId): array
    {
        if (isset(self::$permissionCache[$userId])) {
            return self::$permissionCache[$userId];
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare('SELECT menu_key FROM user_permissions WHERE user_id = ?');
            $stmt->execute([$userId]);
            $keys = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\PDOException $e) {
            $keys = [];
        }

        self::$permissionCache[$userId] = $keys;
        return $keys;
    }

    public static function forget(?int $userId = null): void
    {
        if ($userId === null) {
            self::$recordCache = [];
            self::$permissionCache = [];
            return;
        }
        unset(self::$recordCache[$userId], self::$permissionCache[$userId]);
    }

    /**
     * Baris user dari DB (di-cache per request).
     */
    public static function record(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        if (array_key_exists($userId, self::$recordCache)) {
            return self::$recordCache[$userId];
        }
        return self::$recordCache[$userId] = User::find($userId);
    }

    /**
     * Segarkan data sesi dari DB setiap request:
     *  - akun dinonaktifkan / dihapus  → sesi diputus (paksa login ulang)
     *  - nama & role diperbarui        → perubahan admin langsung berlaku
     *
     * Dipanggil sekali oleh front controller sebelum pengecekan izin.
     */
    public static function refreshSession(): void
    {
        if (empty($_SESSION['auth_user']['id'])) {
            return;
        }

        $record = self::record((int) $_SESSION['auth_user']['id']);

        if (!$record || (int) $record['is_active'] !== 1) {
            unset($_SESSION['auth_user']);
            return;
        }

        $_SESSION['auth_user']['username'] = $record['username'];
        $_SESSION['auth_user']['name'] = $record['display_name'] ?: $record['username'];
        $_SESSION['auth_user']['role'] = $record['role'] ?? 'user';
    }
}

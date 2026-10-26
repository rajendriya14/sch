<?php
// Model user: kredensial, role, dan izin menu.
// Password selalu disimpan sebagai hash.
namespace Models;

use Config\Database;
use PDO;

class User
{
    public const ROLES = ['admin' => 'Administrator', 'user' => 'User'];

    /** Username: huruf, angka, titik, garis bawah, strip. */
    private const USERNAME_PATTERN = '/^[A-Za-z0-9._-]{3,50}$/';
    public const MIN_PASSWORD = 3;

    public static function findByUsername(string $username): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Cocokkan username + password. Mengembalikan baris user bila valid
     * (dan akunnya aktif), atau null bila tidak.
     */
    public static function verifyCredentials(string $username, string $password): ?array
    {
        $user = self::findByUsername($username);
        if (!$user || (int) $user['is_active'] !== 1) {
            return null;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    public static function updatePassword(int $id, string $password): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        return $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function recordLogin(int $id): void
    {
        $db = Database::getInstance();
        $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$id]);
    }

    public static function all(): array
    {
        $db = Database::getInstance();
        return $db->query(
            'SELECT u.id, u.username, u.display_name, u.role, u.is_active, u.last_login_at, u.created_at,
                    (SELECT COUNT(*) FROM user_permissions p WHERE p.user_id = u.id) AS permission_count
             FROM users u
             ORDER BY u.role, u.username'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Jumlah administrator aktif (opsional kecualikan satu id).
     * Dipakai untuk mencegah sistem kehilangan admin terakhir.
     */
    public static function countAdmins(int $excludeId = 0): int
    {
        $db = Database::getInstance();
        $sql = 'SELECT COUNT(*) FROM users WHERE role = "admin" AND is_active = 1';
        $params = [];
        if ($excludeId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Buat user baru.
     *
     * @return array{success:bool,error:string,id:int}
     */
    public static function create(array $data): array
    {
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = ($data['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if (!preg_match(self::USERNAME_PATTERN, $username)) {
            return ['success' => false, 'error' => 'Username 3–50 karakter, hanya huruf/angka/titik/garis bawah/strip.', 'id' => 0];
        }
        if (strlen($password) < self::MIN_PASSWORD) {
            return ['success' => false, 'error' => 'Password minimal ' . self::MIN_PASSWORD . ' karakter.', 'id' => 0];
        }
        if (self::findByUsername($username)) {
            return ['success' => false, 'error' => 'Username "' . $username . '" sudah dipakai.', 'id' => 0];
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO users (username, password_hash, display_name, role, is_active) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            trim((string) ($data['display_name'] ?? '')) ?: null,
            $role,
            !empty($data['is_active']) ? 1 : 0,
        ]);

        return ['success' => true, 'error' => '', 'id' => (int) $db->lastInsertId()];
    }

    /**
     * Ubah user. Password hanya diganti bila fieldnya diisi.
     *
     * @return array{success:bool,error:string}
     */
    public static function update(int $id, array $data): array
    {
        $user = self::find($id);
        if (!$user) {
            return ['success' => false, 'error' => 'User tidak ditemukan.'];
        }

        $role = ($data['role'] ?? $user['role']) === 'admin' ? 'admin' : 'user';
        $isActive = !empty($data['is_active']) ? 1 : 0;

        // Cegah terkunci: administrator aktif terakhir tidak boleh hilang.
        $wasActiveAdmin = $user['role'] === 'admin' && (int) $user['is_active'] === 1;
        $willBeActiveAdmin = $role === 'admin' && $isActive === 1;
        if ($wasActiveAdmin && !$willBeActiveAdmin && self::countAdmins($id) === 0) {
            return ['success' => false, 'error' => 'Ini administrator aktif terakhir — tidak bisa diturunkan atau dinonaktifkan.'];
        }

        $fields = 'display_name = ?, role = ?, is_active = ?';
        $params = [
            trim((string) ($data['display_name'] ?? '')) ?: null,
            $role,
            $isActive,
        ];

        $password = (string) ($data['password'] ?? '');
        if ($password !== '') {
            if (strlen($password) < self::MIN_PASSWORD) {
                return ['success' => false, 'error' => 'Password minimal ' . self::MIN_PASSWORD . ' karakter.'];
            }
            $fields = 'password_hash = ?, ' . $fields;
            array_unshift($params, password_hash($password, PASSWORD_DEFAULT));
        }

        $params[] = $id;
        $db = Database::getInstance();
        $db->prepare('UPDATE users SET ' . $fields . ' WHERE id = ?')->execute($params);

        return ['success' => true, 'error' => ''];
    }

    /**
     * @return array{success:bool,error:string}
     */
    public static function delete(int $id): array
    {
        $user = self::find($id);
        if (!$user) {
            return ['success' => false, 'error' => 'User tidak ditemukan.'];
        }
        if ($user['role'] === 'admin' && self::countAdmins($id) === 0) {
            return ['success' => false, 'error' => 'Ini administrator aktif terakhir — tidak bisa dihapus.'];
        }

        $db = Database::getInstance();
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        return ['success' => true, 'error' => ''];
    }

    // ---------------------------------------------------------------
    // Izin menu
    // ---------------------------------------------------------------

    public static function getPermissions(int $userId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT menu_key FROM user_permissions WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Ganti seluruh izin user dengan daftar baru (hanya key yang valid).
     */
    public static function setPermissions(int $userId, array $menuKeys, array $validKeys): void
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$userId]);

            $insert = $db->prepare('INSERT IGNORE INTO user_permissions (user_id, menu_key) VALUES (?, ?)');
            foreach (array_unique($menuKeys) as $key) {
                if (in_array($key, $validKeys, true)) {
                    $insert->execute([$userId, $key]);
                }
            }

            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            throw $e;
        }
    }
}

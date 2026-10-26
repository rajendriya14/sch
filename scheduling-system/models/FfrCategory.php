<?php
// Kategori pengeluaran/pemasukan (Makanan, Transport, Mandi, Dapur, …).
namespace Models;

use Config\Database;
use PDO;

class FfrCategory
{
    public static function all(bool $activeOnly = true): array
    {
        $db = Database::getInstance();
        $sql = 'SELECT * FROM ffr_categories';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        return $db->query($sql . ' ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM ffr_categories WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Ambil berdasarkan nama; buat baru bila belum ada.
     * Dipakai oleh jalan pintas "kategori baru" saat mengisi item belanja.
     */
    public static function findOrCreate(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM ffr_categories WHERE name = ?');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $db->prepare('INSERT INTO ffr_categories (name) VALUES (?)')->execute([$name]);
        return (int) $db->lastInsertId();
    }

    public static function create(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['success' => false, 'error' => 'Nama kategori wajib diisi.'];
        }
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM ffr_categories WHERE name = ?');
        $stmt->execute([$name]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['success' => false, 'error' => 'Kategori "' . $name . '" sudah ada.'];
        }
        $db->prepare('INSERT INTO ffr_categories (name) VALUES (?)')->execute([$name]);
        return ['success' => true, 'error' => ''];
    }

    public static function rename(int $id, string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['success' => false, 'error' => 'Nama kategori wajib diisi.'];
        }
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM ffr_categories WHERE name = ? AND id <> ?');
        $stmt->execute([$name, $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['success' => false, 'error' => 'Nama kategori sudah dipakai.'];
        }
        $db->prepare('UPDATE ffr_categories SET name = ? WHERE id = ?')->execute([$name, $id]);
        return ['success' => true, 'error' => ''];
    }

    public static function delete(int $id): bool
    {
        $db = Database::getInstance();
        return $db->prepare('DELETE FROM ffr_categories WHERE id = ?')->execute([$id]);
    }
}

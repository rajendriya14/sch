<?php

namespace Models;

use Config\Database;

/**
 * Master kategori "Tabunganku".
 *
 * Menentukan kategori investasi mana yang muncul di form input
 * (show_in_form) dan mana yang ikut ditampilkan di portofolio
 * (show_in_list). Urutan tampil diatur lewat sort_order.
 *
 * Baris dibuat otomatis untuk setiap jenis di FfrSavingsAsset::TYPES,
 * jadi menambah jenis baru tidak perlu menambah data manual.
 */
class FfrSavingsType
{
    /** Pengaturan bawaan: semua kategori aktif & tampil. */
    public static function defaults(): array
    {
        $rows = [];
        $order = 0;
        foreach (FfrSavingsAsset::TYPES as $key => $meta) {
            $rows[$key] = [
                'type_key'     => $key,
                'label'        => $meta['label'],
                'icon'         => $meta['icon'],
                'class'        => $meta['class'],
                'show_in_form' => 1,
                'show_in_list' => 1,
                'sort_order'   => ++$order,
                'count'        => 0,
            ];
        }
        return $rows;
    }

    /**
     * Semua kategori + pengaturannya, terurut sesuai sort_order.
     *
     * @return array<string, array> key => pengaturan
     */
    public static function all(): array
    {
        $rows = self::defaults();

        try {
            $db = Database::getInstance();
            self::ensureRows($db);

            $stmt = $db->query(
                'SELECT type_key, show_in_form, show_in_list, sort_order
                 FROM ffr_savings_types'
            );
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $key = (string) $row['type_key'];
                if (!isset($rows[$key])) {
                    continue;
                }
                $rows[$key]['show_in_form'] = (int) $row['show_in_form'];
                $rows[$key]['show_in_list'] = (int) $row['show_in_list'];
                $rows[$key]['sort_order']   = (int) $row['sort_order'];
            }
        } catch (\PDOException $e) {
            // Tabel belum siap — pakai pengaturan bawaan.
        }

        uasort($rows, function (array $a, array $b): int {
            return [$a['sort_order'], $a['label']] <=> [$b['sort_order'], $b['label']];
        });

        return $rows;
    }

    /** Kategori yang boleh dipilih di form input. */
    public static function formKeys(): array
    {
        $keys = [];
        foreach (self::all() as $key => $row) {
            if (!empty($row['show_in_form'])) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /** Kategori yang ikut ditampilkan di portofolio. */
    public static function listKeys(): array
    {
        $keys = [];
        foreach (self::all() as $key => $row) {
            if (!empty($row['show_in_list'])) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Simpan pengaturan dari form master.
     *
     * @param array $posted types[type_key][show_in_form|show_in_list|sort_order]
     * @return bool false bila tabel belum siap
     */
    public static function save(array $posted): bool
    {
        try {
            $db = Database::getInstance();
            self::ensureRows($db);

            $stmt = $db->prepare(
                'UPDATE ffr_savings_types
                 SET show_in_form = ?, show_in_list = ?, sort_order = ?
                 WHERE type_key = ?'
            );

            foreach (FfrSavingsAsset::TYPES as $key => $meta) {
                $row = $posted[$key] ?? [];
                $stmt->execute([
                    !empty($row['show_in_form']) ? 1 : 0,
                    !empty($row['show_in_list']) ? 1 : 0,
                    (int) ($row['sort_order'] ?? 0),
                    $key,
                ]);
            }
        } catch (\PDOException $e) {
            return false;
        }

        return true;
    }

    /** Kembalikan semua kategori ke pengaturan bawaan. */
    public static function reset(): bool
    {
        $rows = [];
        foreach (self::defaults() as $key => $row) {
            $rows[$key] = [
                'show_in_form' => 1,
                'show_in_list' => 1,
                'sort_order'   => (int) $row['sort_order'],
            ];
        }
        return self::save($rows);
    }

    /** Pastikan setiap jenis punya satu baris pengaturan (sekali per request). */
    private static function ensureRows(\PDO $db): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $stmt = $db->prepare(
            'INSERT IGNORE INTO ffr_savings_types (type_key, show_in_form, show_in_list, sort_order)
             VALUES (?, 1, 1, ?)'
        );
        $order = 0;
        foreach (FfrSavingsAsset::TYPES as $key => $meta) {
            $stmt->execute([$key, ++$order]);
        }

        $done = true;
    }
}

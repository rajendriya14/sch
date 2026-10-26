<?php
/**
 * Katalog menu aplikasi — satu sumber kebenaran untuk:
 *   - daftar centang di Manajemen User,
 *   - penjaga akses di public/index.php,
 *   - penyusunan menu sidebar.
 *
 * Menambah menu baru cukup di sini: tambahkan satu entri, maka otomatis
 * muncul sebagai pilihan izin dan bisa dibatasi per user.
 */
namespace Config;

class MenuCatalog
{
    /**
     * key => [label, icon, group, selalu_bisa]
     *
     * 'always' = true berarti semua user yang sudah login boleh membuka
     * (dipakai untuk Portal, karena itu halaman pendarat setelah login).
     */
    private const MENUS = [
        'portal'            => ['label' => 'Portal',                    'icon' => 'bi-grid-1x2',       'group' => 'Utama',       'always' => true],
        'dashboard'         => ['label' => 'Dashboard',                 'icon' => 'bi-speedometer2',   'group' => 'Utama'],
        'bak'               => ['label' => 'Berita Acara Kerja (BAK)',  'icon' => 'bi-receipt',        'group' => 'Utama'],
        'billing'           => ['label' => 'Billing Management System', 'icon' => 'bi-cash-stack',     'group' => 'Utama'],

        'schedule'          => ['label' => 'Schedule',                  'icon' => 'bi-table',          'group' => 'Content Moderator — Scheduling'],
        'tl_schedule'       => ['label' => 'Input TL Schedule',         'icon' => 'bi-pencil-square',  'group' => 'Content Moderator — Scheduling'],
        'schedule_overview' => ['label' => 'Schedule Overview',         'icon' => 'bi-calendar2-week', 'group' => 'Content Moderator — Scheduling'],

        'shifts'            => ['label' => 'Shift Master',              'icon' => 'bi-clock',          'group' => 'Content Moderator — Configuration'],
        'staff'             => ['label' => 'Manage Staff',              'icon' => 'bi-people',         'group' => 'Content Moderator — Configuration'],
        'backdate'          => ['label' => 'Backdate Schedule',         'icon' => 'bi-calendar-range', 'group' => 'Content Moderator — Configuration'],
        'forecast'          => ['label' => 'Forecast Requirements',     'icon' => 'bi-graph-up',       'group' => 'Content Moderator — Configuration'],
        'leave'             => ['label' => 'Leave Request',             'icon' => 'bi-file-text',      'group' => 'Content Moderator — Configuration'],

        'ffr'               => ['label' => 'Family Financial Report',   'icon' => 'bi-piggy-bank',     'group' => 'Keuangan'],
    ];

    public static function all(): array
    {
        return self::MENUS;
    }

    public static function keys(): array
    {
        return array_keys(self::MENUS);
    }

    /**
     * Menu yang bisa dicentang admin. Portal dikecualikan karena selalu terbuka.
     */
    public static function assignable(): array
    {
        return array_filter(self::MENUS, fn(array $m) => empty($m['always']));
    }

    /**
     * Menu yang dapat dicentang, dikelompokkan: group => [key => info].
     */
    public static function assignableGroups(): array
    {
        $groups = [];
        foreach (self::assignable() as $key => $menu) {
            $groups[$menu['group']][$key] = $menu;
        }
        return $groups;
    }

    /**
     * Izin bawaan untuk user baru (tanpa Manajemen User — itu khusus admin).
     */
    public static function defaultUserPermissions(): array
    {
        return array_keys(self::assignable());
    }

    public static function exists(string $key): bool
    {
        return isset(self::MENUS[$key]);
    }

    public static function label(string $key): string
    {
        return self::MENUS[$key]['label'] ?? $key;
    }

    public static function isAlways(string $key): bool
    {
        return !empty(self::MENUS[$key]['always']);
    }
}

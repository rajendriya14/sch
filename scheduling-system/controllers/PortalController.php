<?php
// Controller halaman Portal — menu pilihan setelah login.
namespace Controllers;

use Services\AccessService;
use Services\AuthService;

class PortalController
{
    /**
     * URL tujuan kartu "Family Financial Report".
     *
     * Modul FFR kini hidup di aplikasi ini (page=ffr). Kalau nanti
     * dipindah jadi project terpisah, cukup ganti nilai ini dengan URL
     * project tersebut — mis. 'https://finance.iferror.web.id/'.
     */
    private const FINANCE_URL = 'index.php?page=ffr';

    /**
     * Menu pilihan (kotak-kotak) — halaman pendarat setelah login.
     *
     * Kartu hanya ditampilkan bila user punya izin menunya, supaya tidak
     * ada kartu yang berujung halaman "Akses Ditolak".
     */
    public function index(): void
    {
        $hideSidebar  = true;
        $hideAppLabel = true;   // label abu-abu "Workforce Automation …" — dibuang di Portal
        $hideBrand    = false;  // brand kiri: "Pradiya Portal"
        $brandTitle   = 'Pradiya Portal';
        $brandIcon    = 'bi-grid-1x2';
        $pageName     = 'portal';
        $user         = AuthService::user();
        $userName     = $user['name'] ?? '';

        $apps = [
            [
                'menu'        => 'dashboard',
                'title'       => 'Workforce Scheduler',
                'description' => 'Kelola jadwal shift, staff, forecast, dan Berita Acara Kerja (BAK).',
                'icon'        => 'bi-calendar-check',
                'url'         => 'index.php?page=dashboard',
                'badge'       => null,
            ],
            [
                'menu'        => 'ffr',
                'title'       => 'Family Financial Report',
                'description' => 'Catat pemasukan bulanan, bagi gaji ke kelompok dana, dan pantau ke mana uang pergi.',
                'icon'        => 'bi-piggy-bank',
                'url'         => self::FINANCE_URL,
                'badge'       => null,
            ],
        ];

        // Saring sesuai izin; kartu Workforce dianggap terbuka bila salah satu
        // menu di dalamnya boleh diakses.
        $apps = array_values(array_filter($apps, function (array $app) use ($user): bool {
            if ($app['menu'] === 'dashboard') {
                return AccessService::can($user, 'dashboard')
                    || AccessService::can($user, 'bak')
                    || AccessService::can($user, 'shifts')
                    || AccessService::can($user, 'schedule');
            }
            return AccessService::can($user, $app['menu']);
        }));

        // Pintasan pengaturan — teks biasa (bukan kartu), hanya untuk administrator.
        $links = [];
        if (AccessService::isAdmin($user)) {
            $links[] = [
                'label' => 'Manajemen User',
                'icon'  => 'bi-gear',
                'url'   => 'index.php?page=users',
            ];
        }

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/portal/index.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Halaman penanda untuk project keuangan yang belum dibangun.
     *
     * Tampil terpisah dari Workforce Scheduler: tanpa sidebar, tanpa label
     * aplikasi, dan brand navbar sendiri.
     */
    public function finance(): void
    {
        $pageName     = 'portal';
        $hideSidebar  = true;
        $hideAppLabel = true;
        $brandTitle   = 'Family Financial Report';
        $brandIcon    = 'bi-piggy-bank';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/portal/finance.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}

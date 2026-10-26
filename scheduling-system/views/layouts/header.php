<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pradiya Portal</title>
    <!-- Favicon: ikon `bi-calendar-check` yang sama dengan logo di navbar,
         ditanam sebagai data URI SVG agar tidak butuh berkas tambahan.
         Latar kotak membulat #212529 (bg-dark) + glyph putih supaya tetap
         terlihat di tab browser terang maupun gelap. -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%3E%3Crect%20width='32'%20height='32'%20rx='7'%20fill='%23212529'/%3E%3Cg%20transform='translate(6,6)%20scale(1.25)'%20fill='%23fff'%3E%3Cpath%20d='M10.854,7.146a.5.5,0,0,1,0,.708l-3,3a.5.5,0,0,1-.708,0l-1.5-1.5a.5.5,0,1,1,.708-.708L7.5,9.793l2.646-2.647a.5.5,0,0,1,.708,0z'/%3E%3Cpath%20d='M3.5,0a.5.5,0,0,1,.5.5V1h8V.5a.5.5,0,0,1,1,0V1h1a2,2,0,0,1,2,2v11a2,2,0,0,1-2,2H2a2,2,0,0,1-2-2V3a2,2,0,0,1,2-2h1V.5a.5.5,0,0,1,.5-.5zM1,4v10a1,1,0,0,0,1,1h12a1,1,0,0,0,1-1V4H1z'/%3E%3C/g%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="scheduling-system/public/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../public/assets/css/style.css') ?: '1' ?>">
    <style>
        .sidebar-nav a.sub-link.active:link,
        .sidebar-nav a.sub-link.active:visited,
        .sidebar-nav a.sub-link2.active,
        .sidebar-nav a.sub-link2.active:link,
        .sidebar-nav a.sub-link2.active:visited {
            color: #fff !important;
            background-color: #495057 !important;
            border-left: 3px solid transparent !important;
            font-weight: 600 !important;
        }
        .sidebar-link i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
            color: inherit !important;
        }
        /* Offset navbar (#wrapper, .sidebar) dan jarak atas konten sengaja TIDAK
           ditulis di sini: semuanya diatur style.css memakai var(--navbar-height)
           dan var(--content-pad-top) supaya tiap nilai hanya punya satu sumber. */
    </style>
</head>
<body class="page-<?= htmlspecialchars($pageName ?? 'default') ?>">

    <!-- Top Navbar (fixed) -->
    <?php
        // --- Tanggal & jam realtime (zona waktu WIB) ---
        $now      = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $hariId   = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];
        $bulanId  = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];
        $hariIni  = $hariId[$now->format('l')] ?? $now->format('l');
        $tanggal  = $now->format('j') . ' ' . ($bulanId[(int) $now->format('n')] ?? $now->format('F')) . ' ' . $now->format('Y');
        $jam      = $now->format('H:i');
    ?>
    <?php
        // Opsi layout — dipakai modul yang tampil beda dari Workforce Scheduler
        // (mis. Family Financial Report, Portal): tanpa sidebar, tanpa label
        // aplikasi, brand sendiri, atau tanpa brand sama sekali.
        // Semua punya nilai default.
        $hideSidebar  = $hideSidebar  ?? false;
        $hideAppLabel = $hideAppLabel ?? false;
        $hideBrand    = $hideBrand    ?? false;
        $brandTitle   = $brandTitle   ?? 'Workforce Scheduler';
        $brandIcon    = $brandIcon    ?? 'bi-calendar-check';
        $brandUrl     = $brandUrl     ?? 'index.php?page=portal';
        $appLabel     = $appLabel     ?? 'Workforce Automation Scheduling System';

        // Teks di kiri navbar: "Pradiya Portal" di halaman Portal, selain itu
        // memakai nama modul yang dikirim controller ($brandTitle).
        $navTitle = (($pageName ?? '') === 'portal') ? 'Pradiya Portal' : $brandTitle;
    ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top" style="z-index: 1020;">
        <div class="container-fluid position-relative">
            <?php if (empty($hideSidebar)): ?>
            <button class="btn btn-sm btn-outline-light me-2 d-lg-none" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#sidebarCanvas">
                <i class="bi bi-list"></i>
            </button>
            <?php endif; ?>
            <?php if (empty($hideBrand)): ?>
            <a class="navbar-brand" href="<?= htmlspecialchars($brandUrl) ?>">
                <i class="bi <?= htmlspecialchars($brandIcon) ?>"></i> <?= htmlspecialchars($navTitle) ?>
            </a>
            <?php endif; ?>

            <!-- Tengah navbar: hari, tanggal, jam -->
            <span class="navbar-text position-absolute start-50 translate-middle-x text-center d-none d-lg-block"
                  style="white-space: nowrap;">
                <i class="bi bi-calendar3"></i>
                <strong><?= htmlspecialchars($hariIni) ?></strong>,
                <?= htmlspecialchars($tanggal) ?>
                <span class="text-white-50 mx-1"></span>
                <i class="bi bi-clock"></i>
                <span id="headerClock"><?= htmlspecialchars($jam) ?></span> WIB
            </span>

            <?php if (empty($hideAppLabel)): ?>
            <span class="navbar-text text-white-50 small d-none d-md-block ms-auto">
                <?= htmlspecialchars($appLabel) ?>
            </span>
            <?php endif; ?>

            <?php $authUser = \Services\AuthService::user(); ?>
            <?php if ($authUser): ?>
                <div class="dropdown <?= empty($hideAppLabel) ? 'ms-3' : 'ms-auto' ?>">
                    <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <?= htmlspecialchars($authUser['name'] ?? $authUser['username']) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text small text-muted">
                                @<?= htmlspecialchars($authUser['username']) ?>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="index.php?page=auth&action=changePassword">
                                <i class="bi bi-key"></i> Ganti Password
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="index.php?page=auth&action=logout"
                               data-confirm="Logout dari sistem sekarang?">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Sidebar (independent fixed component NOT inside wrapper) -->
    <?php if (empty($hideSidebar)): ?>
    <div class="offcanvas-lg offcanvas-start sidebar border-end" tabindex="-1" id="sidebarCanvas">
        <div class="offcanvas-header bg-dark text-white border-bottom">
            <h5 class="offcanvas-title"><i class="bi bi-list"></i> Navigation</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            <?php
                // Menu sidebar dibatasi izin user. Admin melihat semuanya.
                $__user  = $authUser ?? \Services\AuthService::user();
                $can     = fn(string $key): bool => \Services\AccessService::can($__user, $key);

                $canSchedule = $can('schedule') || $can('tl_schedule') || $can('schedule_overview');
                $canConfig   = $can('shifts') || $can('staff') || $can('backdate');
                $canContent  = $canSchedule || $canConfig;
            ?>
            <nav class="sidebar-nav">
                <ul class="nav flex-column">

                    <!-- PORTAL — kembali ke menu pilihan (selalu bisa dibuka) -->
                    <li class="nav-item">
                        <a class="nav-link sidebar-link <?= ($pageName ?? '') === 'portal' ? 'active' : '' ?>"
                           href="index.php?page=portal">
                            <i class="bi bi-grid-1x2"></i> Portal
                        </a>
                    </li>

                    <?php if ($can('dashboard')): ?>
                    <!-- DASHBOARD -->
                    <li class="nav-item">
                        <a class="nav-link sidebar-link <?= ($pageName ?? '') === 'dashboard' ? 'active' : '' ?>"
                           href="index.php?page=dashboard">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($can('bak')): ?>
                    <!-- BAK - Berita Acara Kerja -->
                    <li class="nav-item">
                        <a class="nav-link sidebar-link <?= ($pageName ?? '') === 'bak' ? 'active' : '' ?>"
                           href="index.php?page=bak">
                            <i class="bi bi-receipt"></i> Berita Acara Kerja (BAK)
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($can('billing')): ?>
                    <!-- Billing Management System (aplikasi eksternal PPM) -->
                    <li class="nav-item">
                        <a class="nav-link sidebar-link"
                           href="https://ppm.iferror.web.id/login.php"
                           target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-cash-stack"></i> Billing Management System
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($canContent): ?>
                    <li class="nav-item">
                        <hr class="sidebar-divider">
                    </li>

                    <!-- Content Moderator (collapsible) -->
                    <li class="nav-item">
                        <a class="nav-link sidebar-link" data-bs-toggle="collapse" href="#contentModMenu"
                           role="button"
                           aria-expanded="<?= in_array($pageName ?? '', ['content', 'backdate', 'shifts', 'staff', 'forecast', 'leave', 'tl_schedule', 'schedule', 'dashboard']) ? 'true' : 'false' ?>">
                            <i class="bi bi-shield-check"></i> Content Moderator
                            <i class="bi bi-chevron-down float-end transition-rotate"></i>
                        </a>
                        <div class="collapse <?= in_array($pageName ?? '', ['content', 'backdate', 'shifts', 'staff', 'forecast', 'leave', 'tl_schedule', 'schedule', 'dashboard']) ? 'show' : '' ?>" id="contentModMenu">
                            <ul class="nav flex-column ms-3">
                                <?php if ($canSchedule): ?>
                                <!-- Scheduling sub-menu -->
                                <li class="nav-item">
                                    <a class="nav-link sidebar-link sub-link" data-bs-toggle="collapse" href="#schedSubMenu2"
                                       role="button"
                                       aria-expanded="<?= in_array($pageName ?? '', ['schedule', 'tl_schedule']) ? 'true' : 'false' ?>">
                                        <i class="bi bi-table"></i> Scheduling
                                        <i class="bi bi-chevron-down float-end transition-rotate"></i>
                                    </a>
                                    <div class="collapse <?= in_array($pageName ?? '', ['schedule', 'tl_schedule', 'schedule_overview']) ? 'show' : '' ?>" id="schedSubMenu2">
                                        <ul class="nav flex-column ms-3">
                                            <?php if ($can('schedule')): ?>
                                            <li class="nav-item"><a class="nav-link sidebar-link sub-link2 <?= ($pageName ?? '') === 'schedule' ? 'active' : '' ?>" href="index.php?page=schedule"><i class="bi bi-table"></i> Schedule</a></li>
                                            <?php endif; ?>
                                            <?php if ($can('tl_schedule')): ?>
                                            <li class="nav-item"><?php $ca = $_GET['action'] ?? 'index'; ?><a class="nav-link sidebar-link sub-link2 <?= ($pageName ?? '') === 'tl_schedule' && $ca === 'input' ? 'active' : '' ?>" href="index.php?page=tl_schedule&action=input"><i class="bi bi-pencil-square"></i> Input</a></li>
                                            <?php endif; ?>
                                            <?php if ($can('schedule_overview')): ?>
                                            <li class="nav-item"><a class="nav-link sidebar-link sub-link2 <?= ($pageName ?? '') === 'schedule_overview' ? 'active' : '' ?>" href="index.php?page=schedule_overview"><i class="bi bi-calendar2-week"></i> Schedule Overview</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </li>
                                <?php endif; // canSchedule ?>

                                <?php if ($canConfig): ?>
                                <!-- Configuration sub-menu -->
                                <li class="nav-item">
                                    <a class="nav-link sidebar-link sub-link" data-bs-toggle="collapse" href="#configSubMenu2"
                                       role="button"
                                       aria-expanded="<?= in_array($pageName ?? '', ['shifts', 'staff', 'backdate', 'forecast', 'leave']) ? 'true' : 'false' ?>">
                                        <i class="bi bi-gear"></i> Configuration
                                        <i class="bi bi-chevron-down float-end transition-rotate"></i>
                                    </a>
                                    <div class="collapse <?= in_array($pageName ?? '', ['shifts', 'staff', 'backdate', 'forecast', 'leave']) ? 'show' : '' ?>" id="configSubMenu2">
                                        <ul class="nav flex-column ms-3">
                                            <?php if ($can('shifts')): ?>
                                            <li class="nav-item"><a class="nav-link sidebar-link sub-link2 <?= ($pageName ?? '') === 'shifts' ? 'active' : '' ?>" href="index.php?page=shifts"><i class="bi bi-clock"></i> Shift Master</a></li>
                                            <?php endif; ?>
                                            <?php if ($can('staff')): ?>
                                            <li class="nav-item"><a class="nav-link sidebar-link sub-link2 <?= ($pageName ?? '') === 'staff' ? 'active' : '' ?>" href="index.php?page=staff"><i class="bi bi-people"></i> Manage Staff</a></li>
                                            <?php endif; ?>
                                            <?php if ($can('backdate')): ?>
                                            <li class="nav-item"><a class="nav-link sidebar-link sub-link2 <?= ($pageName ?? '') === 'backdate' ? 'active' : '' ?>" href="index.php?page=backdate"><i class="bi bi-calendar-range"></i> Backdate Schedule</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </li>
                                <?php endif; // canConfig ?>
                            </ul>
                        </div>
                    </li>
                    <?php endif; // canContent ?>

                    <?php // Menu Family Financial Report sengaja tidak ditampilkan di
                          // sidebar Workforce Scheduler; aksesnya lewat kartu di Portal. ?>

                </ul>
            </nav>
        </div>
    </div>
    <?php endif; ?>

    <!-- Wrapper: Main content pushed right by sidebar width -->
    <div id="wrapper" class="<?= !empty($hideSidebar) ? 'no-sidebar' : '' ?>">
        <main id="mainContent">
            <div class="container-fluid px-4 pb-3">
                <?php if (!empty($_SESSION['flash_message'])): ?>
                <?php // Semua halaman: pesan flash ditampilkan sebagai toast oleh dialog.js. ?>
                <div data-flash-toast data-tone="<?= htmlspecialchars($_SESSION['flash_type'] ?? 'success') ?>"
                     data-message="<?= htmlspecialchars($_SESSION['flash_message']) ?>" hidden></div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
                <?php endif; ?>

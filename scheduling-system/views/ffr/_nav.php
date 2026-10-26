<?php
/**
 * Partial: submenu Family Financial Report + pemilih periode + tab.
 *
 * FFR punya dua submenu:
 *   - Annual Report : laporan keuangan bulanan (default, isi tab di bawah)
 *   - Tabunganku    : daftar aset & investasi (tidak terikat periode)
 *
 * Variabel yang dipakai: $period, $periodYears, $ffrActive, $periodLabel,
 *                        $ffrSubmenu ('annual' | 'savings')
 */
use Services\FfrService;

$ffrActive = $ffrActive ?? 'dashboard';
$ffrSubmenu = $ffrSubmenu ?? 'annual';
$periodValue = $period['period'] ?? date('Y-m');
$periodLabel = $periodLabel ?? $periodValue;
$isClosed = ($period['status'] ?? 'open') === 'closed';

// Bulan & tahun aktif, dipakai untuk menandai pilihan di dua dropdown.
$activeYear  = (int) substr($periodValue, 0, 4);
$activeMonth = (int) substr($periodValue, 5, 2);
$periodYears = $periodYears ?? (new FfrService())->yearOptions($periodValue);

$tabs = [
    'dashboard'      => ['label' => 'Dashboard',      'icon' => 'bi-speedometer2', 'action' => 'index'],
    'plan'           => ['label' => 'Rencana Bulan',  'icon' => 'bi-wallet2',      'action' => 'plan'],
    'items'          => ['label' => 'Daftar Belanja', 'icon' => 'bi-list-check',   'action' => 'items'],
    'ledger'         => ['label' => 'Ledger',         'icon' => 'bi-journal-text', 'action' => 'ledger'],
    'recommendation' => ['label' => 'Rekomendasi',    'icon' => 'bi-lightbulb',    'action' => 'recommendation'],
    'setup'          => ['label' => 'Master',         'icon' => 'bi-gear',         'action' => 'setup'],
];

// Dua submenu utama Family Financial Report.
$submenus = [
    'annual'  => ['label' => 'Annual Report', 'icon' => 'bi-calendar-range', 'action' => 'index'],
    'savings' => ['label' => 'Tabunganku',    'icon' => 'bi-piggy-bank',     'action' => 'savings'],
];

// Sub-tab tiap submenu utama.
$subTabs = [
    'annual'  => $tabs,
    'savings' => [
        'savings_dashboard' => ['label' => 'Dashboard',       'icon' => 'bi-graph-up',   'action' => 'savingsDashboard'],
        'savings'           => ['label' => 'Portofolio Aset', 'icon' => 'bi-wallet2',    'action' => 'savings'],
        'savings_mutations' => ['label' => 'Mutasi',          'icon' => 'bi-journal-arrow-down', 'action' => 'savingsMutations'],
        'gold_prices'       => ['label' => 'Harga Emas',      'icon' => 'bi-gem',        'action' => 'goldPrices'],
        'savings_master'    => ['label' => 'Master Kategori', 'icon' => 'bi-sliders',    'action' => 'savingsMaster'],
    ],
];
$periodQuery = '&period=' . urlencode($periodValue);
?>

<div class="ffr-page-head d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <div class="ffr-page-title">
        <h2 class="mb-0"><i class="bi bi-piggy-bank"></i> Family Financial Report</h2>
        <p class="text-muted mb-0">
            <?php if ($ffrSubmenu === 'savings'): ?>
                <?php if ($ffrActive === 'savings_dashboard'): ?>
                    Ringkasan portofolio, tren nilai, dan perbandingan aset
                <?php elseif ($ffrActive === 'savings_master'): ?>
                    Pilih kategori yang ingin diinput &amp; ditampilkan
                <?php elseif ($ffrActive === 'gold_prices'): ?>
                    Harga emas harian per brand &amp; pecahan
                <?php elseif ($ffrActive === 'savings_mutations'): ?>
                    Riwayat setor, tarik/jual, transfer, dan koreksi aset
                <?php else: ?>
                    Portofolio &amp; aset investasi keluarga
                <?php endif; ?>
            <?php else: ?>
                Periode <strong><?= htmlspecialchars($periodLabel) ?></strong>
                <?php if ($isClosed): ?>
                    <span class="badge bg-dark ms-1"><i class="bi bi-lock"></i> Ditutup</span>
                <?php endif; ?>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($ffrSubmenu !== 'savings'): ?>
    <form method="get" action="index.php" class="d-flex align-items-center gap-2" id="ffrPeriodForm">
        <input type="hidden" name="page" value="ffr">
        <input type="hidden" name="action" value="<?= htmlspecialchars($tabs[$ffrActive]['action'] ?? 'index', ENT_QUOTES) ?>">

        <label for="ffrMonth" class="form-label mb-0 text-nowrap small text-muted">Bulan</label>
        <select class="form-select form-select-sm ffr-period-select" id="ffrMonth" name="month">
            <?php foreach (FfrService::MONTHS as $num => $nama): ?>
                <option value="<?= $num ?>" <?= $num === $activeMonth ? 'selected' : '' ?>>
                    <?= htmlspecialchars($nama) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="ffrYear" class="form-label mb-0 text-nowrap small text-muted">Tahun</label>
        <select class="form-select form-select-sm ffr-period-select" id="ffrYear" name="year">
            <?php foreach ($periodYears as $tahun): ?>
                <option value="<?= (int) $tahun ?>" <?= (int) $tahun === $activeYear ? 'selected' : '' ?>>
                    <?= (int) $tahun ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="hidden" name="bucket" value="<?= htmlspecialchars((string) ($_GET['bucket'] ?? '')) ?>">
    </form>
    <?php endif; ?>
</div>

<!-- Submenu FFR di sisi kiri (menurun), isi halaman di sisi kanan -->
<div class="ffr-layout">
    <aside class="ffr-rail">
        <!-- Ringkasan menu saat layar sempit (HP): ketuk untuk buka/tutup -->
        <button type="button" class="ffr-rail-toggle" id="ffrRailToggle"
                aria-controls="ffrRailBody" aria-expanded="false">
            <i class="bi <?= $submenus[$ffrSubmenu]['icon'] ?>"></i>
            <span class="ffr-rail-toggle-text">
                <span class="ffr-rail-kicker"><?= htmlspecialchars($submenus[$ffrSubmenu]['label']) ?></span>
                <span class="ffr-rail-current"><?= htmlspecialchars($subTabs[$ffrSubmenu][$ffrActive]['label'] ?? '') ?></span>
            </span>
            <i class="bi bi-chevron-down ffr-rail-toggle-caret"></i>
        </button>

        <div class="ffr-rail-body" id="ffrRailBody">
        <nav class="ffr-rail-nav">
            <?php foreach ($submenus as $key => $menu): ?>
                <?php
                $isActive   = $ffrSubmenu === $key;
                $groupTabs  = $subTabs[$key] ?? [];
                $hasSubmenu = !empty($groupTabs);
                $submenuId  = 'ffrRailSub_' . $key;
                ?>
                <a class="ffr-rail-link <?= $isActive ? 'active' : '' ?>"
                   href="index.php?page=ffr&action=<?= $menu['action'] ?><?= $periodQuery ?>"
                   <?php if ($hasSubmenu): ?>
                       data-ffr-rail-toggle
                       data-bs-target="#<?= $submenuId ?>"
                       aria-controls="<?= $submenuId ?>"
                       aria-expanded="<?= $isActive ? 'true' : 'false' ?>"
                   <?php endif; ?>>
                    <i class="bi <?= $menu['icon'] ?>"></i>
                    <span><?= htmlspecialchars($menu['label']) ?></span>
                    <i class="bi bi-chevron-<?= $hasSubmenu ? 'down' : 'right' ?> ffr-rail-caret"></i>
                </a>

                <?php if ($hasSubmenu): ?>
                    <div class="collapse ffr-rail-sub <?= $isActive ? 'show' : '' ?>" id="<?= $submenuId ?>">
                        <?php foreach ($groupTabs as $tabKey => $tab): ?>
                            <a class="ffr-rail-sublink <?= $ffrActive === $tabKey ? 'active' : '' ?>"
                               href="index.php?page=ffr&action=<?= $tab['action'] ?><?= $key === 'annual' ? $periodQuery : '' ?>">
                                <i class="bi <?= $tab['icon'] ?>"></i>
                                <span><?= htmlspecialchars($tab['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="ffr-rail-foot">
            <a href="index.php?page=portal" class="btn btn-sm btn-outline-secondary w-100 ffr-portal-link">
                <i class="bi bi-grid-1x2"></i> Portal
            </a>
        </div>
        </div><!-- /.ffr-rail-body -->
    </aside>

    <div class="ffr-body">

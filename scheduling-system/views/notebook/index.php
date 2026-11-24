<?php
/**
 * View: Personal Notebook — Minimalist Calendar, Kanban & Assistant (Excel Green)
 */
$userDisplayName = (string) ($user['name'] ?? $user['full_name'] ?? $user['username'] ?? 'User');
$userName = htmlspecialchars($userDisplayName, ENT_QUOTES, 'UTF-8');
?>
<style>
    :root {
        --excel-green: #217346;
        --excel-green-hover: #185c37;
        --excel-green-soft: #eaf5ee;
        --excel-green-border: #b7dfc7;
        --nb-border: #e5e7eb;
        --nb-text: #111827;
        --nb-muted: #6b7280;
        --nb-sidebar-w: 260px;
    }

    body.page-notebook {
        --navbar-height: 0px;
        --content-pad-top: 0px;
        --content-pad-x: 0px;
        background: #ffffff !important;
        color: var(--nb-text);
        padding: 0 !important;
        margin: 0 !important;
    }

    body.page-notebook #wrapper,
    body.page-notebook #mainContent {
        padding: 0 !important;
        margin: 0 !important;
    }

    body.page-notebook #mainContent > .container-fluid {
        padding: 0 !important;
        max-width: 100% !important;
    }

    .nb-app-shell {
        display: flex;
        min-height: 100vh;
        background: #ffffff;
        position: relative;
    }

    .nb-sidebar-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.35);
        z-index: 1045;
    }

    .nb-sidebar-backdrop.is-open {
        display: block;
    }

    /* Sidebar */
    .nb-sidebar {
        width: var(--nb-sidebar-w);
        flex-shrink: 0;
        background: #ffffff;
        border-right: 1px solid var(--nb-border);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 14px 12px;
        transition: margin-left 0.2s ease, transform 0.2s ease, opacity 0.2s ease;
    }

    .nb-sidebar.is-collapsed {
        margin-left: calc(var(--nb-sidebar-w) * -1);
        opacity: 0;
        pointer-events: none;
    }

    @media (max-width: 991px) {
        .nb-sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1050;
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.16);
            margin-left: 0 !important;
            transform: translateX(0);
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            overflow-y: auto;
        }
        .nb-sidebar.is-collapsed {
            transform: translateX(-100%);
            margin-left: 0 !important;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        .nb-content {
            padding: 14px 14px 32px;
        }
        .nb-topbar {
            padding: 0 14px;
        }
        .month-cell {
            min-height: 82px;
        }
    }

    @media (max-width: 575px) {
        .nb-content {
            padding: 12px 10px 28px;
        }
        .cal-toolbar {
            padding: 10px 12px;
            gap: 8px;
        }
        .cal-title-text {
            font-size: 0.9rem;
            width: 100%;
            margin-left: 0;
        }
        .month-head-cell {
            padding: 6px 4px;
            font-size: 0.68rem;
            text-align: center;
        }
        .month-cell {
            min-height: 64px;
            padding: 4px 3px 22px 3px;
        }
        .month-day-num {
            bottom: 3px;
            right: 4px;
            font-size: 0.68rem;
            width: 20px;
            height: 20px;
        }
        .m-pill {
            font-size: 0.65rem;
            padding: 2px 4px;
        }
        .m-pill-time {
            display: none;
        }
        .btn-cal-outline, .btn-excel, .btn-cal-ghost {
            font-size: 0.78rem;
            padding: 5px 9px;
        }
    }

    .sidebar-search {
        position: relative;
        margin-bottom: 16px;
    }

    .sidebar-search i {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: 0.85rem;
    }

    .sidebar-search input {
        width: 100%;
        border: 1px solid var(--nb-border);
        border-radius: 9px;
        padding: 7px 10px 7px 32px;
        font-size: 0.83rem;
        outline: none;
        background: #fff;
        color: var(--nb-text);
    }

    .sidebar-search input:focus {
        border-color: var(--excel-green);
        box-shadow: 0 0 0 3px rgba(33, 115, 70, 0.12);
    }

    .sidebar-section-label {
        font-size: 0.72rem;
        font-weight: 500;
        color: var(--nb-muted);
        padding: 4px 10px;
        margin-top: 6px;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .sidebar-nav-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 7px 10px;
        border-radius: 8px;
        font-size: 0.84rem;
        color: #374151;
        cursor: pointer;
        margin-bottom: 2px;
        border: none;
        background: transparent;
        width: 100%;
        text-align: left;
        transition: background 0.12s, color 0.12s;
    }

    .sidebar-nav-item:hover { background: #f3f4f6; color: var(--nb-text); }
    .sidebar-nav-item.active {
        background: var(--excel-green-soft);
        color: var(--excel-green);
        font-weight: 600;
    }

    .sidebar-nav-left { display: flex; align-items: center; gap: 9px; min-width: 0; }
    .sidebar-nav-left i { font-size: 0.92rem; color: #6b7280; width: 16px; text-align: center; }
    .sidebar-nav-item.active .sidebar-nav-left i { color: var(--excel-green); }
    .sidebar-count { font-size: 0.76rem; color: var(--nb-muted); font-weight: 500; }
    .sidebar-footer { border-top: 1px solid var(--nb-border); padding-top: 12px; margin-top: 12px; }

    /* Main Content */
    .nb-main { flex: 1; min-width: 0; background: #ffffff; display: flex; flex-direction: column; }

    .nb-topbar {
        height: 50px;
        border-bottom: 1px solid var(--nb-border);
        padding: 0 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #ffffff;
    }

    .nb-breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.875rem;
        flex-wrap: wrap;
    }

    .nb-bc-link {
        color: #6b7280;
        text-decoration: none;
        cursor: pointer;
        transition: color 0.12s;
    }

    .nb-bc-link:hover {
        color: var(--nb-text);
    }

    .nb-bc-sep {
        color: #9ca3af;
        font-size: 0.72rem;
    }

    .nb-bc-current {
        color: var(--nb-text);
        font-weight: 500;
    }

    .nb-content {
        padding: 18px 24px 36px;
        max-width: 1380px;
        width: 100%;
        margin: 0 auto;
    }

    /* Buttons */
    .btn-excel {
        background: var(--excel-green);
        border: 1px solid var(--excel-green);
        color: #fff;
        font-size: 0.83rem;
        font-weight: 500;
        padding: 6px 14px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s;
    }
    .btn-excel:hover { background: var(--excel-green-hover); border-color: var(--excel-green-hover); color: #fff; }

    .btn-cal-ghost {
        background: #fff;
        border: 1px solid transparent;
        color: var(--nb-text);
        font-size: 0.83rem;
        font-weight: 500;
        padding: 6px 10px;
        border-radius: 8px;
    }
    .btn-cal-ghost:hover { background: #f3f4f6; }

    .btn-cal-outline {
        background: #fff;
        border: 1px solid var(--nb-border);
        color: var(--nb-text);
        font-size: 0.83rem;
        font-weight: 500;
        padding: 6px 12px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-cal-outline:hover { background: #f9fafb; border-color: #d1d5db; }
    .btn-cal-outline.active {
        background: var(--excel-green-soft);
        border-color: var(--excel-green-border);
        color: var(--excel-green);
        font-weight: 600;
    }

    /* Assistant Strip */
    .assistant-card {
        background: #ffffff;
        border: 1px solid var(--nb-border);
        border-radius: 12px;
        padding: 12px 16px;
        margin-bottom: 16px;
    }

    .assistant-icon {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: var(--excel-green-soft);
        color: var(--excel-green);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    /* Calendar Shell */
    .cal-shell {
        background: #ffffff;
        border: 1px solid var(--nb-border);
        border-radius: 12px;
        overflow: visible;
    }

    .cal-toolbar {
        padding: 12px 16px;
        border-bottom: 1px solid var(--nb-border);
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        background: #ffffff;
    }

    .cal-title-text { font-size: 0.98rem; font-weight: 600; color: var(--nb-text); margin-left: 4px; }

    /* Month Grid */
    .month-grid-head {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        border-bottom: 1px solid var(--nb-border);
        background: #fff;
    }
    .month-head-cell { padding: 8px 12px; font-size: 0.75rem; font-weight: 500; color: var(--nb-muted); }
    .month-grid-body { display: grid; grid-template-columns: repeat(7, 1fr); background: var(--nb-border); gap: 1px; }

    .month-cell {
        background: #fff;
        min-height: 108px;
        padding: 6px 6px 26px 6px;
        position: relative;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .month-cell:hover { background: #fafafa; }
    .month-cell.is-today { background: #f4fbf7; border-bottom: 2px solid var(--excel-green); }
    .month-cell.other-month .month-day-num { color: #9ca3af; }
    .month-day-num {
        position: absolute;
        bottom: 6px;
        right: 8px;
        font-size: 0.76rem;
        font-weight: 500;
        color: #374151;
        width: 24px;
        height: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .month-cell.is-today .month-day-num {
        background: var(--excel-green);
        color: #fff;
        border-radius: 50%;
        font-weight: 600;
    }

    /* Pastel Pills */
    .m-pill {
        font-size: 0.74rem;
        padding: 3px 7px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        cursor: pointer;
        border: 1px solid transparent;
        white-space: nowrap;
        overflow: hidden;
    }
    .m-pill.is-done { opacity: 0.55; text-decoration: line-through; }
    .m-pill-left { display: flex; align-items: center; gap: 5px; min-width: 0; overflow: hidden; }
    .m-pill-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
    .m-pill-title { font-weight: 500; color: #111827; overflow: hidden; text-overflow: ellipsis; }
    .m-pill-time { font-size: 0.69rem; color: #6b7280; flex-shrink: 0; }

    .theme-green  { background: #eaf5ee; border-color: #b7dfc7; }
    .theme-green .m-pill-dot  { background: #217346; }
    .theme-slate  { background: #f3f4f6; border-color: #e5e7eb; }
    .theme-slate .m-pill-dot  { background: #374151; }
    .theme-purple { background: #ede9fe; border-color: #ddd6fe; }
    .theme-purple .m-pill-dot { background: #7c3aed; }
    .theme-pink   { background: #fce7f3; border-color: #fbcfe8; }
    .theme-pink .m-pill-dot   { background: #ec4899; }
    .theme-amber  { background: #fef3c7; border-color: #fde68a; }
    .theme-amber .m-pill-dot  { background: #d97706; }
    .theme-blue   { background: #dbeafe; border-color: #bfdbfe; }
    .theme-blue .m-pill-dot   { background: #2563eb; }
    .theme-cyan   { background: #cffafe; border-color: #a5f3fc; }
    .theme-cyan .m-pill-dot   { background: #0891b2; }

    /* Time Grid Views */
    .time-grid-wrapper { overflow-x: auto; }
    .time-grid-header { display: grid; border-bottom: 1px solid var(--nb-border); background: #fff; }
    .time-axis-spacer { width: 70px; flex-shrink: 0; border-right: 1px solid var(--nb-border); }
    .time-col-header { padding: 10px 12px; text-align: center; font-size: 0.82rem; font-weight: 500; border-right: 1px solid var(--nb-border); }
    .time-col-header:last-child { border-right: none; }
    .all-day-row { display: flex; border-bottom: 1px solid var(--nb-border); min-height: 36px; background: #fff; }
    .all-day-label { width: 70px; flex-shrink: 0; border-right: 1px solid var(--nb-border); font-size: 0.73rem; color: var(--nb-muted); display: flex; align-items: center; justify-content: flex-end; padding-right: 10px; }
    .all-day-cols { flex: 1; display: grid; }
    .all-day-cell { border-right: 1px solid var(--nb-border); padding: 4px 6px; display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
    .all-day-cell:last-child { border-right: none; }
    .time-body { display: flex; position: relative; max-height: 500px; overflow-y: auto; }
    .time-axis { width: 70px; flex-shrink: 0; border-right: 1px solid var(--nb-border); background: #fff; }
    .time-slot-label { height: 56px; font-size: 0.71rem; color: var(--nb-muted); padding-right: 10px; display: flex; align-items: flex-start; justify-content: flex-end; transform: translateY(-7px); }
    .time-columns { flex: 1; display: grid; position: relative; }
    .time-col { border-right: 1px solid var(--nb-border); position: relative; }
    .time-col:last-child { border-right: none; }
    .time-hour-cell { height: 56px; border-bottom: 1px solid #f3f4f6; cursor: pointer; }
    .time-hour-cell:hover { background: #f9fafb; }
    .time-hour-cell.is-half { height: 28px; }
    .time-block { position: absolute; left: 4px; right: 4px; border-radius: 8px; padding: 6px 10px; border: 1px solid transparent; cursor: pointer; overflow: hidden; z-index: 2; }
    .time-block.is-done { opacity: 0.55; text-decoration: line-through; }
    .cal-footer-bar { padding: 10px 16px; border-top: 1px solid var(--nb-border); background: #fff; font-size: 0.77rem; color: var(--nb-muted); display: flex; flex-wrap: wrap; align-items: center; gap: 16px; }
    .legend-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }

    /* Kanban Board */
    .kanban-board { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; align-items: start; }
    @media (max-width: 991px) { .kanban-board { grid-template-columns: 1fr; } }
    .kanban-col { background: #ffffff; border: 1px solid var(--nb-border); border-radius: 12px; padding: 14px; min-height: 220px; }
    .kanban-col.drag-over { background: #f4fbf7; border-color: var(--excel-green); }
    .kanban-col-header { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
    .kanban-col-title { font-size: 0.9rem; font-weight: 600; }
    .kanban-count { font-size: 0.72rem; font-weight: 600; color: var(--nb-muted); background: #f3f4f6; border: 1px solid var(--nb-border); border-radius: 6px; padding: 1px 7px; }
    .kanban-card { background: #ffffff; border: 1px solid var(--nb-border); border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; cursor: grab; transition: border-color 0.15s, box-shadow 0.15s; }
    .kanban-card:last-child { margin-bottom: 0; }
    .kanban-card:hover { border-color: var(--excel-green-border); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04); }
    .prio-pill { font-size: 0.7rem; font-weight: 500; padding: 2px 8px; border-radius: 6px; border: 1px solid var(--nb-border); flex-shrink: 0; }
    .prio-High   { color: #b91c1c; border-color: #fecaca; background: #fef2f2; }
    .prio-Medium { color: #374151; border-color: #e5e7eb; background: #f9fafb; }
    .prio-Low    { color: #217346; border-color: #b7dfc7; background: #eaf5ee; }
    .kanban-avatar { width: 20px; height: 20px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; color: #fff; flex-shrink: 0; }

    /* Agenda Drawer */
    .agenda-drawer { background: #fff; border: 1px solid var(--nb-border); border-radius: 12px; padding: 16px; height: 100%; }
    .agenda-item-row { border: 1px solid var(--nb-border); border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; cursor: pointer; }
    .agenda-item-row:hover { border-color: var(--excel-green); }
</style>

<div class="nb-sidebar-backdrop" id="nbSidebarBackdrop" onclick="toggleSidebar(false)"></div>
<div class="nb-app-shell">
    <!-- LEFT SIDEBAR -->
    <aside class="nb-sidebar" id="nbSidebar">
        <div>
            <div class="d-flex d-lg-none align-items-center justify-content-between mb-3 pb-2 border-bottom">
                <span class="fw-bold small" style="color:var(--excel-green);"><i class="bi bi-journal-bookmark me-1"></i>Personal Notebook</span>
                <button type="button" class="btn-close btn-sm" onclick="toggleSidebar(false)" aria-label="Close"></button>
            </div>
            <div class="sidebar-search">
                <i class="bi bi-search"></i>
                <input type="text" id="sidebarSearch" placeholder="Filter notes &amp; tasks" oninput="renderAll()">
            </div>

            <div class="sidebar-section-label">Pinned</div>
            <div id="sidebarPinnedList">
                <div class="px-2 py-1 text-muted" style="font-size:0.78rem;">Belum ada yang disematkan</div>
            </div>

            <div class="sidebar-section-label mt-3">
                <span>All notebooks</span>
                <i class="bi bi-arrow-repeat" style="cursor:pointer;" title="Reset Filter" onclick="selectFilter('ALL', 'Personal Notebook')"></i>
            </div>

            <button type="button" class="sidebar-nav-item active" id="nav-ALL"
                    data-bs-toggle="collapse" data-bs-target="#allNotesSubmenu" aria-expanded="true"
                    onclick="selectFilter('ALL', 'All Notes')">
                <span class="sidebar-nav-left">
                    <i class="bi bi-journal-text"></i>
                    <span>All Notes</span>
                </span>
                <span class="d-flex align-items-center gap-2">
                    <span class="sidebar-count" id="cnt-ALL">0</span>
                    <i class="bi bi-chevron-down" style="font-size:0.72rem; color:#9ca3af;"></i>
                </span>
            </button>
            <div class="collapse show ps-3" id="allNotesSubmenu">
                <button type="button" class="sidebar-nav-item" id="nav-Meeting" onclick="selectFilter('#Meeting', 'Meeting &amp; Sync')">
                    <span class="sidebar-nav-left">
                        <i class="bi bi-chat-square-text"></i>
                        <span>Meeting &amp; Sync</span>
                    </span>
                    <span class="sidebar-count" id="cnt-Meeting">0</span>
                </button>
                <button type="button" class="sidebar-nav-item" id="nav-Personal" onclick="selectFilter('#Personal', 'Personal Notes')">
                    <span class="sidebar-nav-left">
                        <i class="bi bi-journal-bookmark"></i>
                        <span>Personal Notes</span>
                    </span>
                    <span class="sidebar-count" id="cnt-Personal">0</span>
                </button>
            </div>

            <button type="button" class="sidebar-nav-item" id="nav-Kanban" onclick="switchView('kanban')">
                <span class="sidebar-nav-left">
                    <i class="bi bi-kanban"></i>
                    <span>Kanban Board</span>
                </span>
                <span class="sidebar-count" id="cnt-Kanban">0</span>
            </button>

            <button type="button" class="sidebar-nav-item" id="nav-Settings"
                    data-bs-toggle="collapse" data-bs-target="#settingsSubmenu" aria-expanded="true">
                <span class="sidebar-nav-left">
                    <i class="bi bi-sliders"></i>
                    <span>Settings</span>
                </span>
                <i class="bi bi-chevron-down" style="font-size:0.72rem; color:#9ca3af;"></i>
            </button>
            <div class="collapse show ps-3" id="settingsSubmenu">
                <button type="button" class="sidebar-nav-item" id="nav-Prefs" onclick="openSettingsModal(this, 'prefs')">
                    <span class="sidebar-nav-left">
                        <i class="bi bi-toggles"></i>
                        <span>Preferensi Notes</span>
                    </span>
                </button>
                <button type="button" class="sidebar-nav-item" id="nav-Email" onclick="openSettingsModal(this, 'email')">
                    <span class="sidebar-nav-left">
                        <i class="bi bi-envelope-check"></i>
                        <span>Email Reminder</span>
                    </span>
                    <span class="sidebar-count" id="cnt-Email">0</span>
                </button>
            </div>
        </div>

        <div class="sidebar-footer">
            <div class="small text-muted mb-2 px-1" id="sidebarStatusText">
                Email reminder aktif · 0 akun
            </div>
            <button type="button" class="btn-cal-outline w-100 justify-content-center" onclick="openSettingsModal()">
                <i class="bi bi-bell"></i> Atur Email Reminder
            </button>
        </div>
    </aside>

    <!-- MAIN WORKSPACE -->
    <section class="nb-main">
        <header class="nb-topbar">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn-cal-ghost px-2" onclick="toggleSidebar()" title="Toggle Sidebar">
                    <i class="bi bi-layout-sidebar"></i>
                </button>
                <nav aria-label="breadcrumb" class="nb-breadcrumb" id="topbarBreadcrumb">
                    <a href="index.php?page=portal" class="nb-bc-link">Home</a>
                    <i class="bi bi-chevron-right nb-bc-sep"></i>
                    <span class="nb-bc-current">All Notes</span>
                </nav>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill d-none d-sm-inline-flex align-items-center" style="background:var(--excel-green-soft); color:var(--excel-green); border:1px solid var(--excel-green-border); font-size:0.72rem;">
                    <i class="bi bi-shield-lock me-1"></i>Private
                </span>
                <?php if (!empty($user)): ?>
                <div class="dropdown">
                    <button class="btn-cal-outline dropdown-toggle py-1 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle" style="color:var(--excel-green);"></i>
                        <span class="d-none d-sm-inline"><?= htmlspecialchars((string) ($user['name'] ?? $user['full_name'] ?? $user['username'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                        <li>
                            <span class="dropdown-item-text small text-muted">
                                @<?= htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item small" href="index.php?page=portal">
                                <i class="bi bi-grid me-2"></i>Kembali ke Portal
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item small" href="index.php?page=auth&action=changePassword">
                                <i class="bi bi-key me-2"></i>Ganti Password
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item small text-danger" href="index.php?page=auth&action=logout" data-confirm="Logout dari sistem sekarang?">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </header>

        <div class="nb-content">
            <!-- Compact Assistant Greeting Strip -->
            <div class="assistant-card d-flex align-items-center gap-3">
                <div class="assistant-icon"><i class="bi bi-stars"></i></div>
                <div>
                    <div class="fw-semibold small" id="heroGreeting">Halo, <?= $userName ?>!</div>
                    <div class="text-muted" style="font-size:0.78rem;" id="heroBriefing">Memuat ringkasan...</div>
                </div>
            </div>

            <!-- Main Calendar & Kanban Card -->
            <div class="row g-3">
                <div id="mainShellCol" class="col-lg-12">
                    <div class="cal-shell">
                        <div class="cal-toolbar">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <button type="button" class="btn-cal-ghost" onclick="jumpToToday()">Today</button>
                                <div class="dropdown">
                                    <button class="btn-cal-ghost dropdown-toggle" type="button" data-bs-toggle="dropdown" id="currentViewLabel">
                                        Month
                                    </button>
                                    <ul class="dropdown-menu shadow-sm border">
                                        <li><a class="dropdown-item small" href="javascript:void(0)" onclick="switchView('month')"><i class="bi bi-calendar3 me-2"></i>Month (Bulanan)</a></li>
                                        <li><a class="dropdown-item small" href="javascript:void(0)" onclick="switchView('day')"><i class="bi bi-clock me-2"></i>Day (Time-Block)</a></li>
                                        <li><a class="dropdown-item small" href="javascript:void(0)" onclick="switchView('category')"><i class="bi bi-columns-gap me-2"></i>Category Grid (Kolom)</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item small" href="javascript:void(0)" onclick="switchView('kanban')"><i class="bi bi-kanban me-2"></i>Kanban Notes (Board)</a></li>
                                    </ul>
                                </div>
                                <div class="d-flex align-items-center">
                                    <button type="button" class="btn-cal-ghost px-2" onclick="navigatePrev()"><i class="bi bi-chevron-left"></i></button>
                                    <button type="button" class="btn-cal-ghost px-2" onclick="navigateNext()"><i class="bi bi-chevron-right"></i></button>
                                </div>
                                <span class="cal-title-text" id="toolbarDateTitle">Bulan Tahun</span>
                            </div>

                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <button type="button" class="btn-cal-outline" id="btnToggleAgenda" onclick="toggleAgendaPanel()">
                                    <i class="bi bi-list-ul"></i> Agenda
                                </button>
                                <button type="button" class="btn-cal-outline" onclick="openSettingsModal()">
                                    <i class="bi bi-sliders"></i> Settings
                                </button>
                                <button type="button" class="btn-excel" onclick="openTaskModal()">
                                    <i class="bi bi-plus-lg"></i> New event
                                </button>
                            </div>
                        </div>

                        <!-- VIEW 1: MONTH -->
                        <div id="view-month">
                            <div class="month-grid-head">
                                <div class="month-head-cell">Sun</div>
                                <div class="month-head-cell">Mon</div>
                                <div class="month-head-cell">Tue</div>
                                <div class="month-head-cell">Wed</div>
                                <div class="month-head-cell">Thu</div>
                                <div class="month-head-cell">Fri</div>
                                <div class="month-head-cell">Sat</div>
                            </div>
                            <div class="month-grid-body" id="monthGridBody"></div>
                        </div>

                        <!-- VIEW 2: CATEGORY GRID -->
                        <div id="view-category" class="d-none">
                            <div class="time-grid-wrapper">
                                <div class="time-grid-header d-flex">
                                    <div class="time-axis-spacer"></div>
                                    <div class="flex-grow-1 d-grid" style="grid-template-columns: repeat(2, 1fr);">
                                        <div class="time-col-header">#Meeting · Sync</div>
                                        <div class="time-col-header">#Personal · Notes</div>
                                    </div>
                                </div>
                                <div class="all-day-row">
                                    <div class="all-day-label">All day</div>
                                    <div class="all-day-cols" style="grid-template-columns: repeat(2, 1fr);" id="catAllDayRow"></div>
                                </div>
                                <div class="time-body">
                                    <div class="time-axis" id="catTimeAxis"></div>
                                    <div class="time-columns" style="grid-template-columns: repeat(2, 1fr);" id="catTimeCols"></div>
                                </div>
                            </div>
                            <div class="cal-footer-bar">
                                <span><span class="legend-dot" style="background:#7c3aed;"></span>#Meeting</span>
                                <span><span class="legend-dot" style="background:#217346;"></span>#Personal</span>
                            </div>
                        </div>

                        <!-- VIEW 3: DAY TIME-BLOCK -->
                        <div id="view-day" class="d-none">
                            <div class="time-grid-wrapper">
                                <div class="time-grid-header d-flex">
                                    <div class="time-axis-spacer"></div>
                                    <div class="flex-grow-1">
                                        <div class="time-col-header text-start ps-3" id="dayViewColHeader">Sun 22</div>
                                    </div>
                                </div>
                                <div class="all-day-row">
                                    <div class="all-day-label">All day</div>
                                    <div class="all-day-cols" style="grid-template-columns: 1fr;" id="dayAllDayRow"></div>
                                </div>
                                <div class="time-body">
                                    <div class="time-axis" id="dayTimeAxis"></div>
                                    <div class="time-columns" style="grid-template-columns: 1fr;" id="dayTimeCols"></div>
                                </div>
                            </div>
                            <div class="cal-footer-bar">
                                <span>Klik pada slot waktu kosong untuk menjadwalkan task atau reminder baru.</span>
                            </div>
                        </div>

                        <!-- VIEW 4: KANBAN BOARD -->
                        <div id="view-kanban" class="d-none p-3" style="background:#ffffff;">
                            <div class="kanban-board" id="kanbanBoard"></div>
                        </div>
                    </div>
                </div>

                <!-- AGENDA SIDEBAR DRAWER -->
                <div id="agendaCol" class="col-lg-4 d-none">
                    <div class="agenda-drawer">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-list-check me-1" style="color:var(--excel-green);"></i> Agenda &amp; Reminder</h6>
                            <button type="button" class="btn-close btn-sm" onclick="toggleAgendaPanel()"></button>
                        </div>
                        <div class="mb-3">
                            <input type="text" id="agendaSearch" class="form-control form-control-sm" placeholder="Cari agenda..." oninput="renderAgendaDrawer()">
                        </div>
                        <div id="agendaDrawerList" style="max-height: 500px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- MODAL: NEW / EDIT EVENT -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold" id="taskModalLabel">New event</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form onsubmit="handleTaskSubmit(event)">
                <input type="hidden" id="taskId" value="0">
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Judul Agenda / Catatan <span class="text-danger">*</span></label>
                        <input type="text" id="taskTitle" required class="form-control" placeholder="Tuliskan judul agenda atau catatan...">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Dari Tanggal</label>
                            <input type="date" id="taskDate" required class="form-control form-control-sm" onchange="syncEndDateMin()">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Sampai Tanggal</label>
                            <input type="date" id="taskEndDate" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Mulai</label>
                            <input type="time" id="taskTime" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Durasi (mnt)</label>
                            <input type="number" id="taskDuration" class="form-control form-control-sm" value="60" min="15" step="15">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Prioritas</label>
                            <select id="taskPriority" class="form-select form-select-sm">
                                <option value="High">High</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Warna</label>
                            <select id="taskTheme" class="form-select form-select-sm">
                                <option value="theme-green" selected>Hijau Segar</option>
                                <option value="theme-slate">Abu Netral</option>
                                <option value="theme-purple">Ungu Lembut</option>
                                <option value="theme-pink">Merah Muda</option>
                                <option value="theme-amber">Kuning Hangat</option>
                                <option value="theme-blue">Biru Langit</option>
                                <option value="theme-cyan">Cyan</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Catatan</label>
                        <textarea id="taskNotes" rows="3" class="form-control form-control-sm" placeholder="Tulis detail catatan atau checklist..."></textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="taskPinned">
                        <label class="form-check-label small fw-semibold" for="taskPinned">
                            <i class="bi bi-bookmark me-1" style="color:var(--excel-green);"></i> Sematkan (Pinned) di Sidebar
                        </label>
                    </div>

                    <div class="p-3 rounded-3 bg-light border">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="taskEmailReminder" checked onchange="toggleTaskReminderBox()">
                            <label class="form-check-label small fw-semibold" for="taskEmailReminder">
                                <i class="bi bi-envelope-check me-1" style="color:var(--excel-green);"></i> Kirim Email Reminder
                            </label>
                        </div>
                        <div id="taskReminderBox">
                            <div class="row g-2 mb-2">
                                <div class="col-7">
                                    <label class="form-label mb-1" style="font-size:0.74rem; color:#4b5563;">Frekuensi / Pola Hari</label>
                                    <select id="taskReminderFreq" class="form-select form-select-sm" onchange="toggleTaskFreqFields()">
                                        <option value="once">Sekali (sebelum deadline)</option>
                                        <option value="workdays">Setiap Hari Kerja (Senin–Jumat)</option>
                                        <option value="daily">Setiap Hari (Senin–Minggu)</option>
                                        <option value="custom">Pilih Hari Tertentu</option>
                                    </select>
                                </div>
                                <div class="col-5" id="taskLeadCol">
                                    <label class="form-label mb-1" style="font-size:0.74rem; color:#4b5563;">Waktu</label>
                                    <select id="taskReminderLead" class="form-select form-select-sm">
                                        <option value="15m">15 menit sblm</option>
                                        <option value="1h" selected>1 jam sblm</option>
                                        <option value="1d">1 hari sblm</option>
                                    </select>
                                </div>
                                <div class="col-5 d-none" id="taskSendTimeCol">
                                    <label class="form-label mb-1" style="font-size:0.74rem; color:#4b5563;">Kirim Setiap Jam</label>
                                    <input type="time" id="taskReminderSendTime" class="form-control form-control-sm" value="08:00">
                                </div>
                            </div>

                            <div id="taskCustomDaysWrap" class="d-none mb-2">
                                <div class="d-flex flex-wrap gap-1">
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="task-day-cb me-1" value="Sen" checked>Sen</label>
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="task-day-cb me-1" value="Sel" checked>Sel</label>
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="task-day-cb me-1" value="Rab" checked>Rab</label>
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="task-day-cb me-1" value="Kam" checked>Kam</label>
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="task-day-cb me-1" value="Jum" checked>Jum</label>
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="task-day-cb me-1" value="Sab">Sab</label>
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="task-day-cb me-1" value="Min">Min</label>
                                </div>
                            </div>

                            <label class="form-label mb-1" style="font-size:0.74rem; color:#4b5563;">Kirim ke Email (bisa lebih dari 1 akun)</label>
                            <div id="taskEmailChips" class="d-flex flex-wrap gap-1 mb-1"></div>
                            <div class="input-group input-group-sm">
                                <input type="email" id="taskEmailInput" class="form-control" placeholder="Ketik email lalu tekan Enter..." onkeydown="if(event.key==='Enter'||event.key===','){event.preventDefault();addEmailChip('task');}">
                                <button type="button" class="btn btn-outline-secondary" onclick="addEmailChip('task')">+ Tambah</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top d-flex justify-content-between">
                    <button type="button" id="btnDeleteTask" class="btn btn-outline-danger btn-sm d-none" onclick="deleteTask()">Hapus</button>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn-cal-outline" onclick="toggleDoneFromModal()" id="btnDoneModal">Tandai Selesai</button>
                        <button type="submit" class="btn-excel" id="btnSaveTask">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: SETTINGS (PREFERENSI NOTES & EMAIL REMINDER) -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-sliders me-2" style="color:var(--excel-green);"></i>Settings — Personal Notebook</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active small fw-semibold" id="tabBtnPrefs" data-bs-toggle="tab" data-bs-target="#tabPanePrefs" type="button" role="tab">
                            <i class="bi bi-toggles me-1"></i> Preferensi Notes
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link small fw-semibold" id="tabBtnEmail" data-bs-toggle="tab" data-bs-target="#tabPaneEmail" type="button" role="tab">
                            <i class="bi bi-envelope-check me-1"></i> Email Reminder
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- TAB 1: PREFERENSI NOTES -->
                    <div class="tab-pane fade show active" id="tabPanePrefs" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Tampilan Awal Saat Dibuka</label>
                                <select id="prefDefaultView" class="form-select form-select-sm">
                                    <option value="month">Month (Kalender Bulanan)</option>
                                    <option value="day">Day (Time-Block Harian)</option>
                                    <option value="category">Category Grid</option>
                                    <option value="kanban">Kanban Board</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Hari Pertama Kalender</label>
                                <select id="prefWeekStart" class="form-select form-select-sm">
                                    <option value="sun">Minggu (Sun)</option>
                                    <option value="mon">Senin (Mon)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Durasi Default (menit)</label>
                                <select id="prefDefaultDuration" class="form-select form-select-sm">
                                    <option value="30">30 menit</option>
                                    <option value="60">60 menit</option>
                                    <option value="90">90 menit</option>
                                    <option value="120">120 menit</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Prioritas Default</label>
                                <select id="prefDefaultPriority" class="form-select form-select-sm">
                                    <option value="High">High</option>
                                    <option value="Medium">Medium</option>
                                    <option value="Low">Low</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Warna Default</label>
                                <select id="prefDefaultTheme" class="form-select form-select-sm">
                                    <option value="theme-green">Hijau Segar</option>
                                    <option value="theme-slate">Abu Netral</option>
                                    <option value="theme-purple">Ungu Lembut</option>
                                    <option value="theme-pink">Merah Muda</option>
                                    <option value="theme-amber">Kuning Hangat</option>
                                    <option value="theme-blue">Biru Langit</option>
                                    <option value="theme-cyan">Cyan</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="prefShowGreeting" checked>
                                    <label class="form-check-label small fw-semibold" for="prefShowGreeting">
                                        Tampilkan Bar Salam &amp; Ringkasan Asisten di Atas Kalender
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 d-flex justify-content-end">
                            <button type="button" class="btn-excel" onclick="saveSettingsToServer()">
                                <i class="bi bi-check2"></i> Simpan Preferensi
                            </button>
                        </div>
                    </div>

                    <!-- TAB 2: EMAIL REMINDER -->
                    <div class="tab-pane fade" id="tabPaneEmail" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Daftar Email Penerima (Multi-Akun)</label>
                                    <div id="cfgEmailChips" class="d-flex flex-wrap gap-1 mb-2"></div>
                                    <div class="input-group input-group-sm">
                                        <input type="email" id="cfgEmailInput" class="form-control" placeholder="Tambah email lain lalu Enter..." onkeydown="if(event.key==='Enter'||event.key===','){event.preventDefault();addEmailChip('cfg');}">
                                        <button type="button" class="btn btn-outline-secondary" onclick="addEmailChip('cfg')">+ Tambah</button>
                                    </div>
                                </div>
                                <div class="p-3 rounded-3 bg-light border mb-3">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="cfgDigest" checked onchange="syncSettingsPreview()">
                                        <label class="form-check-label small fw-semibold" for="cfgDigest">Jadwal Pengiriman Rutin (Briefing)</label>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label mb-1" style="font-size:0.75rem; color:#4b5563;">Pilih Hari Pengiriman</label>
                                        <select id="cfgDigestFreq" class="form-select form-select-sm" onchange="syncSettingsPreview()">
                                            <option value="workdays" selected>Setiap Hari Kerja (Senin – Jumat)</option>
                                            <option value="daily">Setiap Hari (Senin – Minggu)</option>
                                            <option value="custom">Hari Tertentu</option>
                                        </select>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 small text-muted">
                                        <span>Kirim setiap jam:</span>
                                        <input type="time" id="cfgDigestTime" class="form-control form-control-sm w-auto" value="07:30" oninput="syncSettingsPreview()">
                                        <span>WIB</span>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn-excel flex-grow-1 justify-content-center" onclick="saveSettingsToServer()">
                                        Simpan Pengaturan
                                    </button>
                                    <button type="button" class="btn-cal-outline" id="btnSendTestEmail" onclick="sendTestEmail()">
                                        <i class="bi bi-send"></i> Test Kirim
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 bg-light h-100">
                                    <div class="small fw-bold mb-2 text-muted">PREVIEW EMAIL BRIEFING</div>
                                    <div class="bg-white border rounded-3 p-3 small">
                                        <div class="text-muted mb-1">Kepada: <strong id="prevEmailTo">(Belum ada email)</strong></div>
                                        <div class="text-muted mb-2" style="font-size:0.74rem;">Jadwal: <span id="prevEmailSchedule">Senin – Jumat, pukul 07:30 WIB</span></div>
                                        <div class="fw-bold mb-2" style="color:var(--excel-green);">Agenda Anda Hari Ini:</div>
                                        <div id="prevEmailList" class="text-muted"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1090;" id="toastBox"></div>

<script>
window.NOTEBOOK_DATA     = <?= json_encode($tasks, JSON_UNESCAPED_UNICODE) ?>;
window.NOTEBOOK_SETTINGS = <?= json_encode($settings, JSON_UNESCAPED_UNICODE) ?>;
window.NOTEBOOK_USER     = <?= json_encode($userDisplayName, JSON_UNESCAPED_UNICODE) ?>;
</script>

<script>
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const DAYS_SHORT = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const HOURS = [8, 9, 10, 11, 12, 13, 14, 15, 16, 17];
const CATEGORIES = ['#Meeting', '#Personal'];

function relDate(offset) {
    const d = new Date();
    d.setDate(d.getDate() + offset);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

let events = Array.isArray(window.NOTEBOOK_DATA) ? window.NOTEBOOK_DATA : [];
let cfgSettings = window.NOTEBOOK_SETTINGS || { emails: [], digest_enabled: true, digest_freq: 'workdays', digest_time: '07:30', preferences: {} };
let cfgPrefs = Object.assign({
    default_view: 'month',
    default_duration: 60,
    default_priority: 'Medium',
    default_theme: 'theme-green',
    week_start: 'sun',
    show_greeting: true
}, cfgSettings.preferences || {});
let cfgEmails = Array.isArray(cfgSettings.emails) ? [...cfgSettings.emails] : [];
let taskEmails = [];

let currentView = cfgPrefs.default_view || 'month';
let currentDate = new Date();
let activeTagFilter = 'ALL';
let agendaOpen = false;

function applyNotePreferences() {
    document.getElementById('prefDefaultView').value = cfgPrefs.default_view || 'month';
    document.getElementById('prefWeekStart').value = cfgPrefs.week_start || 'sun';
    document.getElementById('prefDefaultDuration').value = String(cfgPrefs.default_duration || 60);
    document.getElementById('prefDefaultPriority').value = cfgPrefs.default_priority || 'Medium';
    document.getElementById('prefDefaultTheme').value = cfgPrefs.default_theme || 'theme-green';
    document.getElementById('prefShowGreeting').checked = cfgPrefs.show_greeting !== false;

    const greetingCard = document.querySelector('.assistant-card');
    if (greetingCard) greetingCard.classList.toggle('d-none', cfgPrefs.show_greeting === false);
}

function initNotebook() {
    const h = new Date().getHours();
    const greetPrefix = h < 11 ? 'Selamat Pagi' : h < 15 ? 'Selamat Siang' : h < 18 ? 'Selamat Sore' : 'Selamat Malam';
    document.getElementById('heroGreeting').textContent = `${greetPrefix}, ${window.NOTEBOOK_USER || 'Bes'}!`;

    document.getElementById('cfgDigest').checked = !!cfgSettings.digest_enabled;
    document.getElementById('cfgDigestFreq').value = cfgSettings.digest_freq || 'workdays';
    document.getElementById('cfgDigestTime').value = cfgSettings.digest_time || '07:30';
    applyNotePreferences();

    if (window.innerWidth < 992) {
        document.getElementById('nbSidebar').classList.add('is-collapsed');
    }

    switchView(currentView);
}

function toggleSidebar(forceOpen = null) {
    const sb = document.getElementById('nbSidebar');
    const bd = document.getElementById('nbSidebarBackdrop');
    const shouldCollapse = forceOpen === null ? !sb.classList.contains('is-collapsed') : !forceOpen;
    sb.classList.toggle('is-collapsed', shouldCollapse);
    if (window.innerWidth < 992) {
        bd.classList.toggle('is-open', !shouldCollapse);
    } else {
        bd.classList.remove('is-open');
    }
}

function renderBreadcrumb(navId) {
    const bc = document.getElementById('topbarBreadcrumb');
    if (!bc) return;

    const trails = {
        'nav-ALL':      [{ label: 'Home', href: 'index.php?page=portal' }, { label: 'All Notes' }],
        'nav-Meeting':  [{ label: 'Home', href: 'index.php?page=portal' }, { label: 'All Notes', onclick: "selectFilter('ALL', 'All Notes')" }, { label: 'Meeting & Sync' }],
        'nav-Personal': [{ label: 'Home', href: 'index.php?page=portal' }, { label: 'All Notes', onclick: "selectFilter('ALL', 'All Notes')" }, { label: 'Personal Notes' }],
        'nav-Kanban':   [{ label: 'Home', href: 'index.php?page=portal' }, { label: 'Kanban Board' }],
        'nav-Prefs':    [{ label: 'Home', href: 'index.php?page=portal' }, { label: 'Settings', onclick: "openSettingsModal(this, 'prefs')" }, { label: 'Preferensi Notes' }],
        'nav-Email':    [{ label: 'Home', href: 'index.php?page=portal' }, { label: 'Settings', onclick: "openSettingsModal(this, 'prefs')" }, { label: 'Email Reminder' }]
    };

    const items = trails[navId] || trails['nav-ALL'];
    bc.innerHTML = items.map((item, idx) => {
        const isLast = idx === items.length - 1;
        if (isLast) return `<span class="nb-bc-current">${esc(item.label)}</span>`;
        if (item.href) return `<a href="${item.href}" class="nb-bc-link">${esc(item.label)}</a>`;
        return `<span class="nb-bc-link" onclick="${item.onclick}">${esc(item.label)}</span>`;
    }).join('<i class="bi bi-chevron-right nb-bc-sep"></i>');
}

function setActiveSidebarNav(navId, title) {
    document.querySelectorAll('.sidebar-nav-item').forEach(b => b.classList.remove('active'));
    const el = document.getElementById(navId);
    if (el) el.classList.add('active');
    renderBreadcrumb(navId);
    if (window.innerWidth < 992) toggleSidebar(false);
}

function selectFilter(tag, title) {
    activeTagFilter = tag;
    const navId = tag === '#Meeting' ? 'nav-Meeting' : (tag === '#Personal' ? 'nav-Personal' : 'nav-ALL');
    setActiveSidebarNav(navId, title || 'All Notes');
    if (currentView === 'kanban') {
        switchView('month');
        return;
    }
    renderAll();
}

function getFilteredEvents() {
    const q = (document.getElementById('sidebarSearch').value || '').toLowerCase().trim();
    return events.filter(ev => {
        if (activeTagFilter !== 'ALL' && ev.tag !== activeTagFilter) return false;
        if (q && !`${ev.title} ${ev.notes || ''} ${ev.tag || ''}`.toLowerCase().includes(q)) return false;
        return true;
    });
}

function renderAll() {
    updateSidebarCounts();
    renderPinnedSidebar();
    renderBriefing();
    updateToolbarTitle();
    if (currentView === 'month') renderMonthView();
    if (currentView === 'category') renderCategoryView();
    if (currentView === 'day') renderDayView();
    if (currentView === 'kanban') renderKanbanView();
    if (agendaOpen) renderAgendaDrawer();
}

function updateSidebarCounts() {
    document.getElementById('cnt-ALL').textContent = events.length;
    document.getElementById('cnt-Meeting').textContent = events.filter(e => e.tag === '#Meeting').length;
    document.getElementById('cnt-Personal').textContent = events.filter(e => e.tag === '#Personal').length;
    const emailCnt = events.filter(e => e.email_reminder && e.status !== 'Done').length;
    document.getElementById('cnt-Email').textContent = emailCnt;
    document.getElementById('cnt-Kanban').textContent = events.length;
    document.getElementById('sidebarStatusText').textContent = `Email reminder aktif · ${cfgEmails.length} akun`;
}

function renderPinnedSidebar() {
    const pinned = events.filter(e => e.is_pinned);
    const el = document.getElementById('sidebarPinnedList');
    el.innerHTML = pinned.length
        ? pinned.map(ev => `
            <button type="button" class="sidebar-nav-item" onclick="openTaskModal(${ev.id})">
                <span class="sidebar-nav-left">
                    <i class="bi bi-bookmark-fill" style="color:var(--excel-green);"></i>
                    <span class="text-truncate">${esc(ev.title)}</span>
                </span>
            </button>
        `).join('')
        : '<div class="px-2 py-1 text-muted" style="font-size:0.78rem;">Belum ada yang disematkan</div>';
}

function renderBriefing() {
    const today = relDate(0);
    const todayCount = events.filter(e => e.due_date === today && e.status !== 'Done').length;
    const emailCount = events.filter(e => e.email_reminder && e.status !== 'Done').length;
    document.getElementById('heroBriefing').innerHTML = (todayCount || emailCount)
        ? `Hari ini ada <strong>${todayCount} agenda</strong> dan <strong>${emailCount} email reminder</strong> aktif.`
        : 'Belum ada agenda hari ini. Klik <strong>+ New event</strong> untuk mulai mencatat.';
}

function switchView(view) {
    currentView = view;
    const labels = { month: 'Month', day: 'Day', category: 'Category', kanban: 'Kanban' };
    document.getElementById('currentViewLabel').textContent = labels[view];
    ['month', 'day', 'category', 'kanban'].forEach(v => {
        document.getElementById('view-' + v).classList.toggle('d-none', v !== view);
    });

    if (view === 'kanban') {
        setActiveSidebarNav('nav-Kanban', 'Kanban Board');
    } else {
        const navMap = { 'ALL': ['nav-ALL', 'All Notes'], '#Meeting': ['nav-Meeting', 'Meeting & Sync'], '#Personal': ['nav-Personal', 'Personal Notes'] };
        const [navId, title] = navMap[activeTagFilter] || ['nav-ALL', 'All Notes'];
        setActiveSidebarNav(navId, title);
    }
    renderAll();
}

function updateToolbarTitle() {
    const el = document.getElementById('toolbarDateTitle');
    if (currentView === 'month') el.textContent = `${MONTHS[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
    else if (currentView === 'kanban') el.textContent = 'Notes & Task Board';
    else el.textContent = currentDate.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

function jumpToToday() { currentDate = new Date(); renderAll(); }
function navigatePrev() {
    if (currentView === 'month') currentDate.setMonth(currentDate.getMonth() - 1);
    else currentDate.setDate(currentDate.getDate() - 1);
    renderAll();
}
function navigateNext() {
    if (currentView === 'month') currentDate.setMonth(currentDate.getMonth() + 1);
    else currentDate.setDate(currentDate.getDate() + 1);
    renderAll();
}

function dateStr(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function formatShortTime(t) {
    if (!t) return '';
    const [hh, mm] = t.split(':').map(Number);
    return `${hh % 12 || 12}:${String(mm).padStart(2, '0')} ${hh >= 12 ? 'PM' : 'AM'}`;
}

/* MONTH VIEW */
function renderMonthView() {
    const y = currentDate.getFullYear(), m = currentDate.getMonth();
    const weekStartMon = (cfgPrefs.week_start === 'mon');
    const headLabels = weekStartMon
        ? ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']
        : ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const headRow = document.querySelector('.month-grid-head');
    if (headRow) {
        headRow.innerHTML = headLabels.map(d => `<div class="month-head-cell">${d}</div>`).join('');
    }

    let firstIdx = new Date(y, m, 1).getDay();
    if (weekStartMon) firstIdx = (firstIdx + 6) % 7;

    const lastDay = new Date(y, m + 1, 0).getDate();
    const prevLast = new Date(y, m, 0).getDate();
    const todayStr = relDate(0);
    const list = getFilteredEvents();

    const grid = document.getElementById('monthGridBody');
    grid.innerHTML = '';

    const cells = [];
    for (let x = firstIdx; x > 0; x--) cells.push({ d: new Date(y, m - 1, prevLast - x + 1), other: true });
    for (let i = 1; i <= lastDay; i++) cells.push({ d: new Date(y, m, i), other: false });
    const rem = (cells.length > 35 ? 42 : 35) - cells.length;
    for (let j = 1; j <= rem; j++) cells.push({ d: new Date(y, m + 1, j), other: true });

    cells.forEach(c => {
        const ds = dateStr(c.d);
        const cell = document.createElement('div');
        cell.className = 'month-cell' + (c.other ? ' other-month' : '') + (ds === todayStr ? ' is-today' : '');
        cell.onclick = () => openTaskModal(null, ds);

        list.filter(e => ds >= e.due_date && ds <= (e.end_date || e.due_date)).slice(0, 3).forEach(ev => {
            const pill = document.createElement('div');
            pill.className = `m-pill ${ev.theme || 'theme-green'} ${ev.status === 'Done' ? 'is-done' : ''}`;
            pill.onclick = (e) => { e.stopPropagation(); openTaskModal(ev.id); };
            pill.innerHTML = `
                <span class="m-pill-left">
                    <span class="m-pill-dot"></span>
                    <span class="m-pill-title">${esc(ev.title)}</span>
                </span>
                <span class="m-pill-time">${ev.duration_minutes === 30 ? '30m' : formatShortTime(ev.due_time)}</span>
            `;
            cell.appendChild(pill);
        });

        const num = document.createElement('div');
        num.className = 'month-day-num';
        num.textContent = c.d.getDate();
        cell.appendChild(num);
        grid.appendChild(cell);
    });
}

/* CATEGORY GRID VIEW */
function renderCategoryView() {
    const ds = dateStr(currentDate);
    document.getElementById('catTimeAxis').innerHTML = HOURS.map(h => `<div class="time-slot-label">${h > 12 ? h - 12 + ' PM' : h === 12 ? '12 PM' : h + ' AM'}</div>`).join('');
    const dayEvents = getFilteredEvents().filter(e => ds >= e.due_date && ds <= (e.end_date || e.due_date));

    document.getElementById('catAllDayRow').innerHTML = CATEGORIES.map(cat => {
        const allDay = dayEvents.filter(e => e.tag === cat && !e.due_time);
        return `<div class="all-day-cell">${allDay.map(e => `<span class="m-pill ${e.theme || 'theme-green'}" onclick="openTaskModal(${e.id})">${esc(e.title)}</span>`).join('')}</div>`;
    }).join('');

    const colsEl = document.getElementById('catTimeCols');
    colsEl.innerHTML = '';
    CATEGORIES.forEach(cat => {
        const col = document.createElement('div');
        col.className = 'time-col';
        HOURS.forEach(h => {
            const slot = document.createElement('div');
            slot.className = 'time-hour-cell';
            slot.onclick = () => openTaskModal(null, ds, `${String(h).padStart(2, '0')}:00`);
            col.appendChild(slot);
        });
        dayEvents.filter(e => e.tag === cat && e.due_time).forEach(ev => {
            const block = createTimeBlockEl(ev, true);
            if (block) col.appendChild(block);
        });
        colsEl.appendChild(col);
    });
}

/* DAY TIME-BLOCK VIEW */
function renderDayView() {
    const ds = dateStr(currentDate);
    document.getElementById('dayViewColHeader').textContent = `${DAYS_SHORT[currentDate.getDay()]} ${currentDate.getDate()}`;
    const axis = document.getElementById('dayTimeAxis');
    axis.innerHTML = '';
    HOURS.forEach(h => {
        axis.innerHTML += `<div class="time-slot-label" style="height:28px;">${h > 12 ? h - 12 + ' PM' : h === 12 ? '12 PM' : h + ' AM'}</div>`;
        axis.innerHTML += `<div class="time-slot-label" style="height:28px;">${h > 12 ? h - 12 : h}:30 ${h >= 12 ? 'PM' : 'AM'}</div>`;
    });

    const dayEvents = getFilteredEvents().filter(e => ds >= e.due_date && ds <= (e.end_date || e.due_date));
    document.getElementById('dayAllDayRow').innerHTML = `<div class="all-day-cell">${dayEvents.filter(e => !e.due_time).map(e => `<span class="m-pill ${e.theme || 'theme-green'}" onclick="openTaskModal(${e.id})">${esc(e.title)}</span>`).join('')}</div>`;

    const cols = document.getElementById('dayTimeCols');
    cols.innerHTML = '';
    const col = document.createElement('div');
    col.className = 'time-col';
    HOURS.forEach(h => {
        ['00', '30'].forEach(mm => {
            const slot = document.createElement('div');
            slot.className = 'time-hour-cell is-half';
            slot.onclick = () => openTaskModal(null, ds, `${String(h).padStart(2, '0')}:${mm}`);
            col.appendChild(slot);
        });
    });
    dayEvents.filter(e => e.due_time).forEach(ev => {
        const block = createTimeBlockEl(ev, false);
        if (block) col.appendChild(block);
    });
    cols.appendChild(col);
}

function createTimeBlockEl(ev, compact) {
    const [hh, mm] = ev.due_time.split(':').map(Number);
    if (hh < 8 || hh > 17) return null;
    const topPx = ((hh - 8) * 60 + mm) * (56 / 60);
    const heightPx = Math.max(36, (ev.duration_minutes || 60) * (56 / 60));

    const el = document.createElement('div');
    el.className = `time-block ${ev.theme || 'theme-green'} ${ev.status === 'Done' ? 'is-done' : ''}`;
    el.style.top = topPx + 'px';
    el.style.height = heightPx + 'px';
    el.onclick = (e) => { e.stopPropagation(); openTaskModal(ev.id); };

    el.innerHTML = compact
        ? `<div class="fw-semibold small text-truncate">${esc(ev.title)}</div><div class="text-muted" style="font-size:0.72rem;">${formatShortTime(ev.due_time)}</div>`
        : `<div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="fw-semibold small">${esc(ev.title)}</div>
                    <div style="font-size:0.74rem; opacity:0.8;">${formatShortTime(ev.due_time)} (${ev.duration_minutes || 60}m)</div>
                </div>
                ${ev.email_reminder ? '<span class="badge bg-white text-dark border" style="font-size:0.68rem;"><i class="bi bi-bell-fill me-1" style="color:var(--excel-green);"></i>Email</span>' : ''}
           </div>`;
    return el;
}

/* KANBAN BOARD */
function formatKanbanDate(ds) {
    if (!ds) return '';
    const [y, m, d] = ds.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function renderKanbanView() {
    const cols = [
        { status: 'Todo', label: 'Backlog' },
        { status: 'In Progress', label: 'In Progress' },
        { status: 'Done', label: 'Done' }
    ];
    const list = getFilteredEvents();

    document.getElementById('kanbanBoard').innerHTML = cols.map(c => {
        const items = list.filter(e => e.status === c.status);
        return `
            <div class="kanban-col"
                 ondragover="event.preventDefault(); this.classList.add('drag-over');"
                 ondragleave="this.classList.remove('drag-over');"
                 ondrop="handleKanbanDrop(event, '${c.status}')">
                <div class="kanban-col-header">
                    <span class="kanban-col-title">${c.label}</span>
                    <span class="kanban-count">${items.length}</span>
                </div>
                <div>
                    ${items.length ? items.map(ev => {
                        const prio = ev.priority || 'Medium';
                        return `
                            <div class="kanban-card" draggable="true"
                                 ondragstart="event.dataTransfer.setData('text/plain', '${ev.id}')"
                                 onclick="openTaskModal(${ev.id})">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <span class="fw-semibold" style="font-size:0.87rem;">${esc(ev.title)}</span>
                                    <span class="prio-pill prio-${prio}">${prio}</span>
                                </div>
                                ${ev.notes ? `<div class="text-muted text-truncate mb-2" style="font-size:0.75rem;">${esc(ev.notes)}</div>` : ''}
                                <div class="d-flex align-items-center justify-content-between pt-1">
                                    <span class="text-muted" style="font-size:0.75rem;">${ev.email_reminder ? '<i class="bi bi-envelope-check me-1" style="color:var(--excel-green);"></i>Reminder' : ''}</span>
                                    <span class="text-muted" style="font-size:0.75rem;">${ev.end_date && ev.end_date !== ev.due_date ? `${formatKanbanDate(ev.due_date)} – ${formatKanbanDate(ev.end_date)}` : formatKanbanDate(ev.due_date)}</span>
                                </div>
                            </div>
                        `;
                    }).join('') : `<div class="text-center text-muted py-4" style="font-size:0.78rem; border:1px dashed var(--nb-border); border-radius:10px;">Kosong</div>`}
                </div>
            </div>
        `;
    }).join('');
}

async function handleKanbanDrop(e, newStatus) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');
    const id = Number(e.dataTransfer.getData('text/plain'));
    const ev = events.find(x => x.id === id);
    if (!ev || ev.status === newStatus) return;

    const prevStatus = ev.status;
    ev.status = newStatus;
    renderAll();

    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('status', newStatus);
        const res = await fetch('index.php?page=notebook&action=toggleStatus', {
            method: 'POST', body: fd, headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (!json.success) {
            ev.status = prevStatus;
            renderAll();
        } else {
            showToast(`"${ev.title}" dipindah ke ${newStatus === 'Todo' ? 'Backlog' : newStatus}.`);
        }
    } catch (err) {
        ev.status = prevStatus;
        renderAll();
    }
}

/* AGENDA DRAWER */
function toggleAgendaPanel() {
    agendaOpen = !agendaOpen;
    document.getElementById('mainShellCol').className = agendaOpen ? 'col-lg-8' : 'col-lg-12';
    document.getElementById('agendaCol').classList.toggle('d-none', !agendaOpen);
    document.getElementById('btnToggleAgenda').classList.toggle('active', agendaOpen);
    if (agendaOpen) renderAgendaDrawer();
}

function renderAgendaDrawer() {
    const q = (document.getElementById('agendaSearch').value || '').toLowerCase();
    const list = getFilteredEvents().filter(e => !q || `${e.title} ${e.notes || ''}`.toLowerCase().includes(q)).sort((a, b) => a.due_date.localeCompare(b.due_date));
    document.getElementById('agendaDrawerList').innerHTML = list.length
        ? list.map(ev => `
            <div class="agenda-item-row ${ev.status === 'Done' ? 'opacity-50' : ''}" onclick="openTaskModal(${ev.id})">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-semibold small ${ev.status === 'Done' ? 'text-decoration-line-through' : ''}">${esc(ev.title)}</span>
                    <span class="prio-pill prio-${ev.priority || 'Medium'}">${ev.priority || 'Medium'}</span>
                </div>
                <div class="text-muted" style="font-size:0.74rem;">
                    ${ev.due_date}${ev.end_date && ev.end_date !== ev.due_date ? ' s/d ' + ev.end_date : ''} ${ev.due_time ? '· ' + formatShortTime(ev.due_time) : ''}
                    ${ev.email_reminder ? ' · <i class="bi bi-envelope" style="color:var(--excel-green);"></i> Reminder' : ''}
                </div>
            </div>
        `).join('')
        : '<div class="text-center text-muted py-4 small">Belum ada agenda tercatat.</div>';
}

/* EMAIL CHIPS & REMINDER OPTIONS */
function toggleTaskReminderBox() {
    document.getElementById('taskReminderBox').classList.toggle('d-none', !document.getElementById('taskEmailReminder').checked);
}

function toggleTaskFreqFields() {
    const freq = document.getElementById('taskReminderFreq').value;
    document.getElementById('taskLeadCol').classList.toggle('d-none', freq !== 'once');
    document.getElementById('taskSendTimeCol').classList.toggle('d-none', freq === 'once');
    document.getElementById('taskCustomDaysWrap').classList.toggle('d-none', freq !== 'custom');
}

function addEmailChip(target) {
    const input = document.getElementById(target === 'cfg' ? 'cfgEmailInput' : 'taskEmailInput');
    const val = input.value.replace(/,/g, '').trim();
    if (!val || !val.includes('@')) return;
    const arr = target === 'cfg' ? cfgEmails : taskEmails;
    if (!arr.includes(val)) arr.push(val);
    input.value = '';
    renderEmailChips(target);
    if (target === 'cfg') { syncSettingsPreview(); updateSidebarCounts(); }
}

function removeEmailChip(target, idx) {
    const arr = target === 'cfg' ? cfgEmails : taskEmails;
    arr.splice(idx, 1);
    renderEmailChips(target);
    if (target === 'cfg') { syncSettingsPreview(); updateSidebarCounts(); }
}

function renderEmailChips(target) {
    const arr = target === 'cfg' ? cfgEmails : taskEmails;
    const container = document.getElementById(target === 'cfg' ? 'cfgEmailChips' : 'taskEmailChips');
    container.innerHTML = arr.map((em, i) => `
        <span class="badge d-inline-flex align-items-center gap-1 fw-normal" style="background:var(--excel-green-soft); color:var(--excel-green); border:1px solid var(--excel-green-border); font-size:0.74rem; padding:4px 8px;">
            ${esc(em)}
            <i class="bi bi-x" style="cursor:pointer;" onclick="removeEmailChip('${target}', ${i})"></i>
        </span>
    `).join('');
}

function syncEndDateMin() {
    const start = document.getElementById('taskDate').value;
    const endEl = document.getElementById('taskEndDate');
    endEl.min = start;
    if (!endEl.value || endEl.value < start) endEl.value = start;
}

/* TASK MODAL CRUD */
function openTaskModal(id = null, defDate = null, defTime = null) {
    const ev = id ? events.find(x => x.id === id) : null;
    document.getElementById('taskModalLabel').textContent = ev ? 'Edit event' : 'New event';
    document.getElementById('taskId').value = ev ? ev.id : 0;
    document.getElementById('taskTitle').value = ev ? ev.title : '';
    const startVal = ev ? ev.due_date : (defDate || dateStr(currentDate));
    document.getElementById('taskDate').value = startVal;
    document.getElementById('taskEndDate').value = ev ? (ev.end_date || startVal) : startVal;
    document.getElementById('taskEndDate').min = startVal;
    document.getElementById('taskTime').value = ev ? (ev.due_time || '') : (defTime || '09:00');
    document.getElementById('taskDuration').value = ev ? (ev.duration_minutes || 60) : (cfgPrefs.default_duration || 60);
    document.getElementById('taskPriority').value = ev ? (ev.priority || 'Medium') : (cfgPrefs.default_priority || 'Medium');
    document.getElementById('taskTheme').value = ev ? (ev.theme || 'theme-green') : (cfgPrefs.default_theme || 'theme-green');
    document.getElementById('taskNotes').value = ev ? (ev.notes || '') : '';
    document.getElementById('taskPinned').checked = ev ? !!ev.is_pinned : false;
    document.getElementById('taskEmailReminder').checked = ev ? !!ev.email_reminder : true;
    document.getElementById('taskReminderFreq').value = ev ? (ev.reminder_freq || 'once') : 'once';
    document.getElementById('taskReminderLead').value = ev ? (ev.reminder_lead || '1h') : '1h';
    document.getElementById('taskReminderSendTime').value = ev ? (ev.reminder_send_time || '08:00') : '08:00';

    const savedDays = ev && Array.isArray(ev.reminder_days_arr) ? ev.reminder_days_arr : ['Sen','Sel','Rab','Kam','Jum'];
    document.querySelectorAll('.task-day-cb').forEach(cb => { cb.checked = savedDays.includes(cb.value); });

    taskEmails = ev && Array.isArray(ev.reminder_emails) ? [...ev.reminder_emails] : [...cfgEmails];
    renderEmailChips('task');
    toggleTaskReminderBox();
    toggleTaskFreqFields();

    document.getElementById('btnDeleteTask').classList.toggle('d-none', !ev);
    document.getElementById('btnDoneModal').classList.toggle('d-none', !ev);
    if (ev) {
        document.getElementById('btnDoneModal').textContent = ev.status === 'Done' ? 'Buka Kembali' : 'Tandai Selesai';
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('taskModal')).show();
}

async function handleTaskSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveTask');
    btn.disabled = true;

    const id = Number(document.getElementById('taskId').value);
    const existing = id ? events.find(x => x.id === id) : null;
    const customDays = Array.from(document.querySelectorAll('.task-day-cb:checked')).map(c => c.value).join(',');

    const fd = new FormData();
    fd.append('id', id);
    fd.append('title', document.getElementById('taskTitle').value.trim());
    fd.append('due_date', document.getElementById('taskDate').value);
    fd.append('end_date', document.getElementById('taskEndDate').value || document.getElementById('taskDate').value);
    fd.append('due_time', document.getElementById('taskTime').value);
    fd.append('duration_minutes', document.getElementById('taskDuration').value);
    fd.append('priority', document.getElementById('taskPriority').value);
    fd.append('status', existing ? existing.status : 'Todo');
    fd.append('tag', existing ? (existing.tag || '#Personal') : (activeTagFilter === '#Meeting' ? '#Meeting' : '#Personal'));
    fd.append('theme', document.getElementById('taskTheme').value);
    fd.append('notes', document.getElementById('taskNotes').value.trim());
    if (document.getElementById('taskPinned').checked) fd.append('is_pinned', '1');
    if (document.getElementById('taskEmailReminder').checked) fd.append('email_reminder', '1');
    fd.append('reminder_freq', document.getElementById('taskReminderFreq').value);
    fd.append('reminder_lead', document.getElementById('taskReminderLead').value);
    fd.append('reminder_send_time', document.getElementById('taskReminderSendTime').value);
    fd.append('reminder_days', customDays);
    fd.append('reminder_emails', JSON.stringify(taskEmails));

    try {
        const res = await fetch('index.php?page=notebook&action=save', {
            method: 'POST', body: fd, headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (json.success && json.task) {
            const idx = events.findIndex(x => x.id == json.task.id);
            if (idx >= 0) events[idx] = json.task;
            else events.push(json.task);
            bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
            renderAll();
            showToast(json.message || 'Agenda tersimpan.');
        } else {
            alert(json.message || 'Gagal menyimpan agenda.');
        }
    } catch (err) {
        alert('Terjadi kesalahan jaringan saat menyimpan.');
    } finally {
        btn.disabled = false;
    }
}

async function toggleDoneFromModal() {
    const id = Number(document.getElementById('taskId').value);
    const ev = events.find(x => x.id === id);
    if (!ev) return;

    const newStatus = ev.status === 'Done' ? 'Todo' : 'Done';
    const fd = new FormData();
    fd.append('id', id);
    fd.append('status', newStatus);

    const res = await fetch('index.php?page=notebook&action=toggleStatus', {
        method: 'POST', body: fd, headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    if (json.success) {
        ev.status = newStatus;
        bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
        renderAll();
    }
}

async function deleteTask() {
    const id = Number(document.getElementById('taskId').value);
    if (!id || !confirm('Hapus agenda ini dari notebook Anda?')) return;

    const fd = new FormData();
    fd.append('id', id);
    const res = await fetch('index.php?page=notebook&action=delete', {
        method: 'POST', body: fd, headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    if (json.success) {
        events = events.filter(x => x.id !== id);
        bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
        renderAll();
        showToast('Agenda dihapus.');
    }
}

/* SETTINGS MODAL */
function openSettingsModal(btnEl = null, tab = 'prefs') {
    if (btnEl) {
        setActiveSidebarNav(tab === 'email' ? 'nav-Email' : 'nav-Prefs', tab === 'email' ? 'Email Reminder' : 'Preferensi Notes');
    }
    applyNotePreferences();
    renderEmailChips('cfg');
    syncSettingsPreview();
    const tabBtn = document.getElementById(tab === 'email' ? 'tabBtnEmail' : 'tabBtnPrefs');
    if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('settingsModal')).show();
}

function syncSettingsPreview() {
    document.getElementById('prevEmailTo').textContent = cfgEmails.length ? cfgEmails.join(', ') : '(Belum ada email)';
    const freqMap = { workdays: 'Senin – Jumat', daily: 'Setiap Hari', custom: 'Hari Tertentu' };
    const freq = document.getElementById('cfgDigestFreq').value;
    const time = document.getElementById('cfgDigestTime').value || '07:30';
    const enabled = document.getElementById('cfgDigest').checked;
    document.getElementById('prevEmailSchedule').textContent = enabled ? `${freqMap[freq]}, pukul ${time} WIB` : 'Nonaktif';

    const todayItems = events.filter(e => e.due_date === relDate(0) && e.status !== 'Done');
    document.getElementById('prevEmailList').innerHTML = todayItems.length
        ? todayItems.map(e => `<div class="py-1 border-bottom">• ${esc(e.title)} (${formatShortTime(e.due_time)})</div>`).join('')
        : 'Tidak ada agenda hari ini.';
}

async function saveSettingsToServer() {
    cfgPrefs = {
        default_view: document.getElementById('prefDefaultView').value,
        week_start: document.getElementById('prefWeekStart').value,
        default_duration: Number(document.getElementById('prefDefaultDuration').value) || 60,
        default_priority: document.getElementById('prefDefaultPriority').value,
        default_theme: document.getElementById('prefDefaultTheme').value,
        show_greeting: document.getElementById('prefShowGreeting').checked
    };

    const fd = new FormData();
    fd.append('emails', JSON.stringify(cfgEmails));
    if (document.getElementById('cfgDigest').checked) fd.append('digest_enabled', '1');
    fd.append('digest_freq', document.getElementById('cfgDigestFreq').value);
    fd.append('digest_time', document.getElementById('cfgDigestTime').value);
    fd.append('preferences', JSON.stringify(cfgPrefs));

    const res = await fetch('index.php?page=notebook&action=saveSettings', {
        method: 'POST', body: fd, headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    if (json.success) {
        cfgSettings = json.settings;
        applyNotePreferences();
        bootstrap.Modal.getInstance(document.getElementById('settingsModal')).hide();
        renderAll();
        showToast(json.message || 'Pengaturan disimpan.');
    }
}

async function sendTestEmail() {
    const btn = document.getElementById('btnSendTestEmail');
    btn.disabled = true;
    try {
        await saveSettingsToServer();
        const res = await fetch('index.php?page=notebook&action=sendTestEmail', {
            method: 'POST', headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        showToast(json.message || (json.success ? 'Email terkirim.' : 'Gagal mengirim email.'));
    } catch (err) {
        showToast('Gagal menghubungi server.');
    } finally {
        btn.disabled = false;
    }
}

function esc(s) {
    return String(s || '').replace(/[&<>'"]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[m]));
}

function showToast(msg) {
    const el = document.createElement('div');
    el.className = 'toast align-items-center border-0 show mb-2 text-white';
    el.style.background = '#217346';
    el.innerHTML = `<div class="d-flex"><div class="toast-body small">${esc(msg)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button></div>`;
    document.getElementById('toastBox').appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

document.addEventListener('DOMContentLoaded', initNotebook);
</script>

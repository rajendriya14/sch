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
        overflow-x: hidden;
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
            padding: 8px 14px;
            min-height: 50px;
            height: auto;
            gap: 8px;
        }
        .nb-breadcrumb {
            font-size: 0.8rem;
            gap: 5px;
        }
        .month-cell {
            min-height: 82px;
        }
        .kanban-board {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575px) {
        .nb-content {
            padding: 10px 8px 28px;
        }
        .nb-topbar {
            padding: 8px 10px;
        }
        .nb-breadcrumb {
            font-size: 0.76rem;
            gap: 4px;
            max-width: calc(100vw - 120px);
        }
        .assistant-card {
            padding: 10px 12px;
            margin-bottom: 12px;
        }
        .cal-toolbar {
            padding: 10px;
            gap: 8px;
            flex-direction: column;
            align-items: stretch;
        }
        .cal-toolbar > div {
            width: 100%;
            justify-content: space-between;
        }
        .cal-title-text {
            font-size: 0.88rem;
            margin-left: auto;
        }
        .month-head-cell {
            padding: 6px 2px;
            font-size: 0.68rem;
            text-align: center;
        }
        .month-cell {
            min-height: 62px;
            padding: 3px 2px 20px 2px;
            gap: 2px;
        }
        .month-day-num {
            bottom: 2px;
            right: 3px;
            font-size: 0.66rem;
            width: 18px;
            height: 18px;
        }
        .m-pill {
            font-size: 0.62rem;
            padding: 1px 4px;
            border-radius: 4px;
            gap: 3px;
        }
        .m-pill-dot {
            width: 5px;
            height: 5px;
        }
        .m-pill-time {
            display: none;
        }
        .btn-cal-outline, .btn-excel, .btn-cal-ghost {
            font-size: 0.76rem;
            padding: 5px 8px;
        }
        .kanban-board {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .time-axis-spacer, .time-axis, .all-day-label {
            width: 52px;
            font-size: 0.65rem;
            padding-right: 6px;
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
        grid-template-columns: repeat(7, minmax(0, 1fr));
        border-bottom: 1px solid var(--nb-border);
        background: #fff;
    }
    .month-head-cell { padding: 8px 12px; font-size: 0.75rem; font-weight: 500; color: var(--nb-muted); min-width: 0; }
    .month-grid-body { display: flex; flex-direction: column; background: var(--nb-border); gap: 1px; }
    .month-week-row { position: relative; }
    .month-week-bg { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); background: var(--nb-border); gap: 1px; }
    .month-week-events {
        position: absolute;
        top: 6px;
        left: 0;
        right: 0;
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        row-gap: 3px;
        column-gap: 0;
        padding: 0 3px;
        pointer-events: none;
        z-index: 2;
    }
    .month-week-events .m-pill {
        pointer-events: auto;
        margin: 0 2px;
    }
    .m-pill.cont-left  { border-top-left-radius: 2px; border-bottom-left-radius: 2px; margin-left: 0; border-left: none; }
    .m-pill.cont-right { border-top-right-radius: 2px; border-bottom-right-radius: 2px; margin-right: 0; border-right: none; }

    .month-cell {
        background: #fff;
        min-height: 108px;
        min-width: 0;
        overflow: hidden;
        padding: 6px 6px 26px 6px;
        position: relative;
        cursor: pointer;
    }
    .month-cell:hover { background: #fafafa; }
    .month-cell.is-today { background: #f4fbf7; border-bottom: 2px solid var(--excel-green); }
    .month-cell.other-month .month-day-num { color: #9ca3af; }
    .month-cell.is-red .month-day-num { color: #dc2626; font-weight: 600; }
    .month-cell.other-month.is-red .month-day-num { color: #fca5a5; }
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
    .month-add-btn {
        position: absolute;
        bottom: 8px;
        right: 34px;
        width: 20px;
        height: 20px;
        border-radius: 6px;
        background: var(--excel-green);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        line-height: 1;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.14s ease, transform 0.14s ease;
        z-index: 3;
    }
    .month-cell:hover .month-add-btn {
        opacity: 1;
    }
    @media (max-width: 575px) {
        .month-add-btn {
            display: none;
        }
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

    /* Sticky Notes */
    .sticky-card {
        border-radius: 12px;
        padding: 16px;
        border: 1px solid rgba(17, 24, 39, 0.08);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.14s ease, box-shadow 0.14s ease;
    }
    .sticky-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px -6px rgba(17, 24, 39, 0.12);
    }
    .sticky-yellow { background: #fef9c3; }
    .sticky-green  { background: #eaf5ee; border-color: #b7dfc7; }
    .sticky-blue   { background: #dbeafe; }
    .sticky-pink   { background: #fce7f3; }
    .sticky-purple { background: #ede9fe; }
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

            <button type="button" class="sidebar-nav-item" id="nav-Sticky" onclick="switchView('sticky')">
                <span class="sidebar-nav-left">
                    <i class="bi bi-sticky"></i>
                    <span>Sticky Notes</span>
                </span>
                <span class="sidebar-count" id="cnt-Sticky">0</span>
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
                <button type="button" class="sidebar-nav-item" id="nav-Holidays" onclick="openSettingsModal(this, 'holidays')">
                    <span class="sidebar-nav-left">
                        <i class="bi bi-calendar-x text-danger"></i>
                        <span>Tanggal Merah</span>
                    </span>
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
                                        <li><a class="dropdown-item small" href="javascript:void(0)" onclick="switchView('sticky')"><i class="bi bi-sticky me-2"></i>Sticky Notes</a></li>
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

                        <!-- VIEW 5: STICKY NOTES -->
                        <div id="view-sticky" class="d-none p-3" style="background:#ffffff;">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <div>
                                    <h6 class="fw-bold mb-0">Sticky Notes &amp; Pengingat Harian</h6>
                                    <span class="text-muted small">Catat cepat dan pilih jam reminder harian (atau pilih tidak di-reminder).</span>
                                </div>
                                <button type="button" class="btn-excel" onclick="openStickyModal()">
                                    <i class="bi bi-plus-lg"></i> Tulis Sticky Note
                                </button>
                            </div>
                            <div class="row g-3" id="stickyNotesGrid"></div>
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

<!-- MODAL: STICKY NOTE -->
<div class="modal fade" id="stickyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold" id="stickyModalLabel">Tulis Sticky Note</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form onsubmit="handleStickySubmit(event)">
                <input type="hidden" id="stickyId" value="0">
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Judul Catatan <span class="text-danger">*</span></label>
                        <input type="text" id="stickyTitle" required class="form-control" placeholder="Misal: Follow up tim, Checklist harian...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Isi Catatan <span class="text-danger">*</span></label>
                        <textarea id="stickyContent" rows="4" required class="form-control" placeholder="Tulis catatan Anda di sini..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Warna Sticky</label>
                        <select id="stickyTheme" class="form-select form-select-sm">
                            <option value="sticky-yellow">Kuning Hangat</option>
                            <option value="sticky-green">Hijau Segar</option>
                            <option value="sticky-blue">Biru Langit</option>
                            <option value="sticky-pink">Merah Muda</option>
                            <option value="sticky-purple">Ungu Lembut</option>
                        </select>
                    </div>

                    <div class="p-3 rounded-3 bg-light border">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="stickyEmailReminder" onchange="toggleStickyReminderBox()">
                            <label class="form-check-label small fw-semibold" for="stickyEmailReminder">
                                <i class="bi bi-envelope-check me-1" style="color:var(--excel-green);"></i> Kirim Email Reminder
                            </label>
                        </div>
                        <div id="stickyReminderBox" class="d-none">
                            <div class="row g-2 mb-2">
                                <div class="col-7">
                                    <label class="form-label mb-1" style="font-size:0.74rem; color:#4b5563;">Frekuensi / Pola Hari</label>
                                    <select id="stickyReminderFreq" class="form-select form-select-sm" onchange="toggleStickyFreqFields()">
                                        <option value="workdays">Setiap Hari Kerja (Senin–Jumat)</option>
                                        <option value="daily" selected>Setiap Hari (Senin–Minggu)</option>
                                        <option value="custom">Pilih Hari Tertentu</option>
                                    </select>
                                </div>
                                <div class="col-5">
                                    <label class="form-label mb-1" style="font-size:0.74rem; color:#4b5563;">Jam 1 (Utama)</label>
                                    <input type="time" id="stickyReminderTime" class="form-control form-control-sm" value="08:00">
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label mb-1" style="font-size:0.74rem; color:#4b5563;">Jam 2 (Opsional)</label>
                                    <input type="time" id="stickyReminderTime2" class="form-control form-control-sm" value="">
                                </div>
                                <div class="col-6">
                                    <label class="form-label mb-1" style="font-size:0.74rem; color:#4b5563;">Jam 3 (Opsional)</label>
                                    <input type="time" id="stickyReminderTime3" class="form-control form-control-sm" value="">
                                </div>
                            </div>

                            <div id="stickyCustomDaysWrap" class="d-none mb-2">
                                <div class="d-flex flex-wrap gap-1">
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="sticky-day-cb me-1" value="Sen" checked>Sen</label>
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="sticky-day-cb me-1" value="Sel" checked>Sel</label>
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="sticky-day-cb me-1" value="Rab" checked>Rab</label>
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="sticky-day-cb me-1" value="Kam" checked>Kam</label>
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="sticky-day-cb me-1" value="Jum" checked>Jum</label>
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="sticky-day-cb me-1" value="Sab">Sab</label>
                                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;"><input type="checkbox" class="sticky-day-cb me-1" value="Min">Min</label>
                                </div>
                            </div>

                            <label class="form-label mb-1" style="font-size:0.74rem; color:#4b5563;">Kirim ke Email (bisa lebih dari 1 akun)</label>
                            <div id="stickyEmailChips" class="d-flex flex-wrap gap-1 mb-1"></div>
                            <div class="input-group input-group-sm">
                                <input type="email" id="stickyEmailInput" class="form-control" placeholder="Ketik email lalu tekan Enter..." onkeydown="if(event.key==='Enter'||event.key===','){event.preventDefault();addEmailChip('sticky');}">
                                <button type="button" class="btn btn-outline-secondary" onclick="addEmailChip('sticky')">+ Tambah</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top d-flex justify-content-between">
                    <button type="button" id="btnDeleteSticky" class="btn btn-outline-danger btn-sm d-none" onclick="deleteStickyFromModal()">Hapus</button>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn-cal-outline" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn-excel" id="btnSaveSticky">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: NEW / EDIT EVENT -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
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
                                    <label class="form-label mb-1" style="font-size:0.74rem; color:#4b5563;">Jam 1 (Utama)</label>
                                    <input type="time" id="taskReminderSendTime" class="form-control form-control-sm" value="08:00">
                                </div>
                            </div>
                            <div class="row g-2 mb-2 d-none" id="taskExtraTimesRow">
                                <div class="col-6">
                                    <label class="form-label mb-1" style="font-size:0.74rem; color:#4b5563;">Jam 2 (Opsional)</label>
                                    <input type="time" id="taskReminderSendTime2" class="form-control form-control-sm" value="">
                                </div>
                                <div class="col-6">
                                    <label class="form-label mb-1" style="font-size:0.74rem; color:#4b5563;">Jam 3 (Opsional)</label>
                                    <input type="time" id="taskReminderSendTime3" class="form-control form-control-sm" value="">
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
    <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
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
                    <li class="nav-item" role="presentation">
                        <button class="nav-link small fw-semibold" id="tabBtnHolidays" data-bs-toggle="tab" data-bs-target="#tabPaneHolidays" type="button" role="tab">
                            <i class="bi bi-calendar-x text-danger me-1"></i> Tanggal Merah
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
                        <?php if (empty($smtpReady)): ?>
                        <div class="alert alert-warning small py-2 px-3 mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>SMTP belum terpasang di server:</strong> Upload file <code>scheduling-system/config/mail.credentials.local.php</code> ke hosting agar email bisa terkirim.
                        </div>
                        <?php endif; ?>
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
                                    <div class="d-flex flex-wrap align-items-center gap-2 small text-muted mb-2">
                                        <span>Jam 1:</span>
                                        <input type="time" id="cfgDigestTime" class="form-control form-control-sm w-auto" value="07:30" oninput="syncSettingsPreview()">
                                        <span>WIB</span>
                                    </div>
                                    <div class="row g-2 small text-muted">
                                        <div class="col-6">
                                            <label class="form-label mb-0" style="font-size:0.72rem;">Jam 2 (Opsional)</label>
                                            <input type="time" id="cfgDigestTime2" class="form-control form-control-sm" value="" oninput="syncSettingsPreview()">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label mb-0" style="font-size:0.72rem;">Jam 3 (Opsional)</label>
                                            <input type="time" id="cfgDigestTime3" class="form-control form-control-sm" value="" oninput="syncSettingsPreview()">
                                        </div>
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

                    <!-- TAB 3: TANGGAL MERAH -->
                    <div class="tab-pane fade" id="tabPaneHolidays" role="tabpanel">
                        <p class="small text-muted mb-3">
                            Tambahkan daftar tanggal merah / hari libur nasional. Tanggal merah dan hari akhir pekan (Sabtu &amp; Minggu) otomatis ditandai berwarna merah di kalender.
                        </p>
                        <div class="row g-2 mb-3 align-items-end">
                            <div class="col-sm-4">
                                <label class="form-label small fw-semibold mb-1">Tanggal</label>
                                <input type="date" id="holidayDateInput" class="form-control form-control-sm">
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label small fw-semibold mb-1">Keterangan (Opsional)</label>
                                <input type="text" id="holidayLabelInput" class="form-control form-control-sm" placeholder="Misal: Hari Natal, Cuti Bersama...">
                            </div>
                            <div class="col-sm-3">
                                <button type="button" class="btn-cal-outline w-100 justify-content-center" onclick="addHoliday()">
                                    <i class="bi bi-plus-lg"></i> Tambah
                                </button>
                            </div>
                        </div>
                        <div id="holidaysList" class="border rounded-3 p-2 bg-light mb-3" style="max-height: 220px; overflow-y: auto;"></div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn-excel" onclick="saveSettingsToServer()">
                                <i class="bi bi-check2"></i> Simpan Tanggal Merah
                            </button>
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
window.NOTEBOOK_STICKIES = <?= json_encode($stickies, JSON_UNESCAPED_UNICODE) ?>;
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
let stickies = Array.isArray(window.NOTEBOOK_STICKIES) ? window.NOTEBOOK_STICKIES : [];
let cfgSettings = window.NOTEBOOK_SETTINGS || { emails: [], digest_enabled: true, digest_freq: 'workdays', digest_time: '07:30', preferences: {} };
let cfgPrefs = Object.assign({
    default_view: 'month',
    default_duration: 60,
    default_priority: 'Medium',
    default_theme: 'theme-green',
    week_start: 'sun',
    show_greeting: true,
    holidays: []
}, cfgSettings.preferences || {});
let cfgHolidays = Array.isArray(cfgPrefs.holidays) ? [...cfgPrefs.holidays] : [];
let cfgEmails = Array.isArray(cfgSettings.emails) ? [...cfgSettings.emails] : [];
let taskEmails = [];
let stickyEmails = [];

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
    document.getElementById('cfgDigestTime2').value = cfgSettings.digest_time_2 || '';
    document.getElementById('cfgDigestTime3').value = cfgSettings.digest_time_3 || '';
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
        'nav-Sticky':   [{ label: 'Home', href: 'index.php?page=portal' }, { label: 'Sticky Notes' }],
        'nav-Prefs':    [{ label: 'Home', href: 'index.php?page=portal' }, { label: 'Settings', onclick: "openSettingsModal(this, 'prefs')" }, { label: 'Preferensi Notes' }],
        'nav-Email':    [{ label: 'Home', href: 'index.php?page=portal' }, { label: 'Settings', onclick: "openSettingsModal(this, 'prefs')" }, { label: 'Email Reminder' }],
        'nav-Holidays': [{ label: 'Home', href: 'index.php?page=portal' }, { label: 'Settings', onclick: "openSettingsModal(this, 'prefs')" }, { label: 'Tanggal Merah' }]
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
    if (currentView === 'sticky') renderStickyView();
    if (agendaOpen) renderAgendaDrawer();
}

function updateSidebarCounts() {
    document.getElementById('cnt-ALL').textContent = events.length;
    document.getElementById('cnt-Meeting').textContent = events.filter(e => e.tag === '#Meeting').length;
    document.getElementById('cnt-Personal').textContent = events.filter(e => e.tag === '#Personal').length;
    const emailCnt = events.filter(e => e.email_reminder && e.status !== 'Done').length;
    document.getElementById('cnt-Email').textContent = emailCnt;
    document.getElementById('cnt-Kanban').textContent = events.length;
    document.getElementById('cnt-Sticky').textContent = stickies.length;
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
    const labels = { month: 'Month', day: 'Day', category: 'Category', kanban: 'Kanban', sticky: 'Sticky Notes' };
    document.getElementById('currentViewLabel').textContent = labels[view] || 'Month';
    ['month', 'day', 'category', 'kanban', 'sticky'].forEach(v => {
        document.getElementById('view-' + v).classList.toggle('d-none', v !== view);
    });

    if (view === 'kanban') {
        setActiveSidebarNav('nav-Kanban', 'Kanban Board');
    } else if (view === 'sticky') {
        setActiveSidebarNav('nav-Sticky', 'Sticky Notes');
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
    else if (currentView === 'sticky') el.textContent = 'Sticky Notes';
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

    const diffDays = (aStr, bStr) => {
        const [ay, am, ad] = aStr.split('-').map(Number);
        const [by, bm, bd] = bStr.split('-').map(Number);
        return Math.round((Date.UTC(ay, am - 1, ad) - Date.UTC(by, bm - 1, bd)) / 86400000);
    };

    for (let w = 0; w < cells.length; w += 7) {
        const weekCells = cells.slice(w, w + 7);
        const weekStart = dateStr(weekCells[0].d);
        const weekEnd   = dateStr(weekCells[6].d);

        const weekRow = document.createElement('div');
        weekRow.className = 'month-week-row';

        const bgGrid = document.createElement('div');
        bgGrid.className = 'month-week-bg';

        // Find events overlapping this week
        const weekEvents = list.filter(ev => {
            const evEnd = ev.end_date || ev.due_date;
            return ev.due_date <= weekEnd && evEnd >= weekStart;
        }).sort((a, b) => {
            const aSpan = diffDays(a.end_date || a.due_date, a.due_date);
            const bSpan = diffDays(b.end_date || b.due_date, b.due_date);
            if (a.due_date !== b.due_date) return a.due_date.localeCompare(b.due_date);
            if (aSpan !== bSpan) return bSpan - aSpan;
            return (a.due_time || '').localeCompare(b.due_time || '');
        });

        // Lane packing (max 3 visible lanes per week row)
        const lanes = [];
        const placements = [];
        const overflowCount = [0, 0, 0, 0, 0, 0, 0];

        weekEvents.forEach(ev => {
            const evEnd = ev.end_date || ev.due_date;
            const startIdx = Math.max(0, diffDays(ev.due_date, weekStart));
            const endIdx   = Math.min(6, diffDays(evEnd, weekStart));

            let placedLane = -1;
            for (let l = 0; l < 3; l++) {
                if (!lanes[l]) lanes[l] = Array(7).fill(false);
                let free = true;
                for (let c = startIdx; c <= endIdx; c++) {
                    if (lanes[l][c]) { free = false; break; }
                }
                if (free) {
                    for (let c = startIdx; c <= endIdx; c++) lanes[l][c] = true;
                    placedLane = l;
                    break;
                }
            }

            if (placedLane >= 0) {
                placements.push({
                    ev,
                    startIdx,
                    endIdx,
                    lane: placedLane,
                    contLeft: ev.due_date < weekStart,
                    contRight: evEnd > weekEnd
                });
            } else {
                for (let c = startIdx; c <= endIdx; c++) overflowCount[c]++;
            }
        });

        weekCells.forEach((c, idx) => {
            const ds = dateStr(c.d);
            const dow = c.d.getDay();
            const isWeekend = (dow === 0 || dow === 6);
            const holiday = cfgHolidays.find(h => h.date === ds);
            const isRed = isWeekend || !!holiday;

            const cell = document.createElement('div');
            cell.className = 'month-cell'
                + (c.other ? ' other-month' : '')
                + (isRed ? ' is-red' : '')
                + (ds === todayStr ? ' is-today' : '');
            if (holiday && holiday.label) {
                cell.title = 'Tanggal Merah: ' + holiday.label;
            }
            cell.onclick = () => openTaskModal(null, ds);

            if (overflowCount[idx] > 0) {
                const more = document.createElement('div');
                more.style.cssText = 'position:absolute;bottom:6px;left:6px;font-size:0.68rem;color:#6b7280;font-weight:500;';
                more.textContent = `+${overflowCount[idx]} lainnya`;
                cell.appendChild(more);
            } else if (holiday && holiday.label) {
                const holBadge = document.createElement('div');
                holBadge.style.cssText = 'position:absolute;bottom:6px;left:6px;right:56px;font-size:0.66rem;color:#dc2626;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:500;';
                holBadge.textContent = holiday.label;
                cell.appendChild(holBadge);
            }

            const addBtn = document.createElement('span');
            addBtn.className = 'month-add-btn';
            addBtn.innerHTML = '<i class="bi bi-plus"></i>';
            cell.appendChild(addBtn);

            const num = document.createElement('div');
            num.className = 'month-day-num';
            num.textContent = c.d.getDate();
            cell.appendChild(num);
            bgGrid.appendChild(cell);
        });

        const evLayer = document.createElement('div');
        evLayer.className = 'month-week-events';
        placements.forEach(p => {
            const ev = p.ev;
            const isMultiDay = (ev.end_date && ev.end_date !== ev.due_date) || p.contLeft || p.contRight;
            const pill = document.createElement('div');
            pill.className = `m-pill ${ev.theme || 'theme-green'} ${ev.status === 'Done' ? 'is-done' : ''}${p.contLeft ? ' cont-left' : ''}${p.contRight ? ' cont-right' : ''}`;
            pill.style.gridColumn = `${p.startIdx + 1} / ${p.endIdx + 2}`;
            pill.style.gridRow = `${p.lane + 1}`;
            pill.title = ev.title;
            pill.onclick = (e) => { e.stopPropagation(); openTaskModal(ev.id); };

            const timeLabel = !isMultiDay
                ? (ev.duration_minutes === 30 ? '30m' : formatShortTime(ev.due_time))
                : '';

            pill.innerHTML = `
                <span class="m-pill-left">
                    <span class="m-pill-dot"></span>
                    <span class="m-pill-title">${esc(ev.title)}</span>
                </span>
                ${timeLabel ? `<span class="m-pill-time">${timeLabel}</span>` : ''}
            `;
            evLayer.appendChild(pill);
        });

        weekRow.appendChild(bgGrid);
        weekRow.appendChild(evLayer);
        grid.appendChild(weekRow);
    }
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

/* STICKY NOTES */
function toggleStickyReminderBox() {
    document.getElementById('stickyReminderBox').classList.toggle('d-none', !document.getElementById('stickyEmailReminder').checked);
}

function toggleStickyFreqFields() {
    const freq = document.getElementById('stickyReminderFreq').value;
    document.getElementById('stickyCustomDaysWrap').classList.toggle('d-none', freq !== 'custom');
}

function renderStickyView() {
    const q = (document.getElementById('sidebarSearch').value || '').toLowerCase().trim();
    const list = stickies.filter(s => !q || `${s.title} ${s.content}`.toLowerCase().includes(q));
    const grid = document.getElementById('stickyNotesGrid');

    if (!list.length) {
        grid.innerHTML = `<div class="col-12"><div class="text-center text-muted py-5 border rounded-3" style="border-style:dashed !important;">Belum ada sticky note. Klik <strong>+ Tulis Sticky Note</strong> untuk mencatat.</div></div>`;
        return;
    }

    const freqLabels = { workdays: 'Senin–Jumat', daily: 'Setiap Hari', custom: 'Hari Tertentu' };
    grid.innerHTML = list.map(s => {
        const times = [s.reminder_time, s.reminder_time_2, s.reminder_time_3].filter(Boolean);
        const remOn = !!s.email_reminder && times.length > 0;
        const emCnt = Array.isArray(s.reminder_emails) ? s.reminder_emails.length : 0;
        return `
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="sticky-card ${s.theme || 'sticky-yellow'}">
                <div>
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <h6 class="fw-bold mb-0">${esc(s.title)}</h6>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <button type="button" class="btn btn-sm btn-light py-0 px-2" onclick="openStickyModal(${s.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-sm btn-light py-0 px-2 text-danger" onclick="deleteSticky(${s.id})" title="Hapus"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                    <p class="small mb-3" style="white-space: pre-line;">${esc(s.content)}</p>
                </div>
                <div class="pt-2 border-top border-dark border-opacity-10 d-flex align-items-center justify-content-between gap-2" style="cursor:pointer;" onclick="openStickyModal(${s.id})">
                    ${remOn
                        ? `<span class="small fw-semibold" style="color:var(--excel-green);"><i class="bi bi-envelope-check me-1"></i>${freqLabels[s.reminder_freq] || 'Setiap Hari'} &middot; ${times.join(', ')} WIB${emCnt ? ` (${emCnt} akun)` : ''}</span>`
                        : `<span class="small text-muted"><i class="bi bi-bell-slash me-1"></i>Tidak di-reminder</span>`}
                    <span class="badge bg-white text-dark border" style="font-size:0.68rem;">Atur</span>
                </div>
            </div>
        </div>
        `;
    }).join('');
}

function openStickyModal(id = null) {
    const s = id ? stickies.find(x => x.id === id) : null;
    document.getElementById('stickyModalLabel').textContent = s ? 'Edit Sticky Note' : 'Tulis Sticky Note';
    document.getElementById('stickyId').value = s ? s.id : 0;
    document.getElementById('stickyTitle').value = s ? s.title : '';
    document.getElementById('stickyContent').value = s ? s.content : '';
    document.getElementById('stickyTheme').value = s ? (s.theme || 'sticky-yellow') : 'sticky-yellow';
    document.getElementById('stickyEmailReminder').checked = s ? !!s.email_reminder : false;
    document.getElementById('stickyReminderFreq').value = s ? (s.reminder_freq || 'daily') : 'daily';
    document.getElementById('stickyReminderTime').value = s ? (s.reminder_time || '08:00') : '08:00';
    document.getElementById('stickyReminderTime2').value = s ? (s.reminder_time_2 || '') : '';
    document.getElementById('stickyReminderTime3').value = s ? (s.reminder_time_3 || '') : '';

    const savedDays = s && Array.isArray(s.reminder_days_arr) && s.reminder_days_arr.length ? s.reminder_days_arr : ['Sen','Sel','Rab','Kam','Jum'];
    document.querySelectorAll('.sticky-day-cb').forEach(cb => { cb.checked = savedDays.includes(cb.value); });

    stickyEmails = s && Array.isArray(s.reminder_emails) && s.reminder_emails.length ? [...s.reminder_emails] : [...cfgEmails];
    renderEmailChips('sticky');
    toggleStickyReminderBox();
    toggleStickyFreqFields();

    document.getElementById('btnDeleteSticky').classList.toggle('d-none', !s);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('stickyModal')).show();
}

async function handleStickySubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveSticky');
    btn.disabled = true;
    const customDays = Array.from(document.querySelectorAll('.sticky-day-cb:checked')).map(c => c.value).join(',');
    const fd = new FormData();
    fd.append('id', document.getElementById('stickyId').value);
    fd.append('title', document.getElementById('stickyTitle').value.trim());
    fd.append('content', document.getElementById('stickyContent').value.trim());
    fd.append('theme', document.getElementById('stickyTheme').value);
    if (document.getElementById('stickyEmailReminder').checked) fd.append('email_reminder', '1');
    fd.append('reminder_freq', document.getElementById('stickyReminderFreq').value);
    fd.append('reminder_days', customDays);
    fd.append('reminder_time', document.getElementById('stickyReminderTime').value);
    fd.append('reminder_time_2', document.getElementById('stickyReminderTime2').value);
    fd.append('reminder_time_3', document.getElementById('stickyReminderTime3').value);
    fd.append('reminder_emails', JSON.stringify(stickyEmails));

    try {
        const res = await fetch('index.php?page=notebook&action=saveSticky', {
            method: 'POST', body: fd, headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (json.success && json.sticky) {
            const idx = stickies.findIndex(x => x.id == json.sticky.id);
            if (idx >= 0) stickies[idx] = json.sticky;
            else stickies.unshift(json.sticky);
            bootstrap.Modal.getInstance(document.getElementById('stickyModal')).hide();
            renderAll();
            showToast(json.message || 'Sticky note disimpan.');
        } else {
            alert(json.message || 'Gagal menyimpan sticky note.');
        }
    } finally {
        btn.disabled = false;
    }
}

async function deleteSticky(id) {
    if (!confirm('Hapus sticky note ini?')) return;
    const fd = new FormData();
    fd.append('id', id);
    const res = await fetch('index.php?page=notebook&action=deleteSticky', {
        method: 'POST', body: fd, headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    if (json.success) {
        stickies = stickies.filter(x => x.id !== id);
        renderAll();
        showToast('Sticky note dihapus.');
    }
}

function deleteStickyFromModal() {
    const id = Number(document.getElementById('stickyId').value);
    if (!id) return;
    bootstrap.Modal.getInstance(document.getElementById('stickyModal')).hide();
    deleteSticky(id);
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
    document.getElementById('taskExtraTimesRow').classList.toggle('d-none', freq === 'once');
    document.getElementById('taskCustomDaysWrap').classList.toggle('d-none', freq !== 'custom');
}

function getEmailTargetArr(target) {
    return target === 'cfg' ? cfgEmails : (target === 'sticky' ? stickyEmails : taskEmails);
}

function addEmailChip(target) {
    const inputId = target === 'cfg' ? 'cfgEmailInput' : (target === 'sticky' ? 'stickyEmailInput' : 'taskEmailInput');
    const input = document.getElementById(inputId);
    const val = input.value.replace(/,/g, '').trim();
    if (!val || !val.includes('@')) return;
    const arr = getEmailTargetArr(target);
    if (!arr.includes(val)) arr.push(val);
    input.value = '';
    renderEmailChips(target);
    if (target === 'cfg') { syncSettingsPreview(); updateSidebarCounts(); }
}

function removeEmailChip(target, idx) {
    const arr = getEmailTargetArr(target);
    arr.splice(idx, 1);
    renderEmailChips(target);
    if (target === 'cfg') { syncSettingsPreview(); updateSidebarCounts(); }
}

function renderEmailChips(target) {
    const arr = getEmailTargetArr(target);
    const containerId = target === 'cfg' ? 'cfgEmailChips' : (target === 'sticky' ? 'stickyEmailChips' : 'taskEmailChips');
    const container = document.getElementById(containerId);
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
    document.getElementById('taskReminderSendTime2').value = ev ? (ev.reminder_send_time_2 || '') : '';
    document.getElementById('taskReminderSendTime3').value = ev ? (ev.reminder_send_time_3 || '') : '';

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
    fd.append('reminder_send_time_2', document.getElementById('taskReminderSendTime2').value);
    fd.append('reminder_send_time_3', document.getElementById('taskReminderSendTime3').value);
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

/* SETTINGS MODAL & TANGGAL MERAH */
function renderHolidaysList() {
    const el = document.getElementById('holidaysList');
    if (!el) return;
    if (!cfgHolidays.length) {
        el.innerHTML = '<div class="text-center text-muted small py-3">Belum ada tanggal merah yang ditambahkan.</div>';
        return;
    }
    const sorted = [...cfgHolidays].sort((a, b) => a.date.localeCompare(b.date));
    el.innerHTML = sorted.map((h, i) => `
        <div class="d-flex align-items-center justify-content-between bg-white border rounded-2 px-3 py-2 mb-1 small">
            <div>
                <strong class="text-danger me-2">${esc(h.date)}</strong>
                <span>${esc(h.label || 'Hari Libur')}</span>
            </div>
            <button type="button" class="btn btn-sm p-0 text-danger" onclick="removeHoliday('${esc(h.date)}')" title="Hapus">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    `).join('');
}

function addHoliday() {
    const dEl = document.getElementById('holidayDateInput');
    const lEl = document.getElementById('holidayLabelInput');
    const dateVal = dEl.value;
    if (!dateVal) return;
    const labelVal = lEl.value.trim() || 'Tanggal Merah';
    const existing = cfgHolidays.find(h => h.date === dateVal);
    if (existing) existing.label = labelVal;
    else cfgHolidays.push({ date: dateVal, label: labelVal });
    dEl.value = '';
    lEl.value = '';
    renderHolidaysList();
}

function removeHoliday(dateVal) {
    cfgHolidays = cfgHolidays.filter(h => h.date !== dateVal);
    renderHolidaysList();
}

function openSettingsModal(btnEl = null, tab = 'prefs') {
    if (btnEl) {
        const navId = tab === 'email' ? 'nav-Email' : (tab === 'holidays' ? 'nav-Holidays' : 'nav-Prefs');
        const title = tab === 'email' ? 'Email Reminder' : (tab === 'holidays' ? 'Tanggal Merah' : 'Preferensi Notes');
        setActiveSidebarNav(navId, title);
    }
    applyNotePreferences();
    renderEmailChips('cfg');
    renderHolidaysList();
    syncSettingsPreview();
    const tabId = tab === 'email' ? 'tabBtnEmail' : (tab === 'holidays' ? 'tabBtnHolidays' : 'tabBtnPrefs');
    const tabBtn = document.getElementById(tabId);
    if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('settingsModal')).show();
}

function syncSettingsPreview() {
    document.getElementById('prevEmailTo').textContent = cfgEmails.length ? cfgEmails.join(', ') : '(Belum ada email)';
    const freqMap = { workdays: 'Senin – Jumat', daily: 'Setiap Hari', custom: 'Hari Tertentu' };
    const freq = document.getElementById('cfgDigestFreq').value;
    const times = [
        document.getElementById('cfgDigestTime').value || '07:30',
        document.getElementById('cfgDigestTime2').value,
        document.getElementById('cfgDigestTime3').value
    ].filter(Boolean).join(', ');
    const enabled = document.getElementById('cfgDigest').checked;
    document.getElementById('prevEmailSchedule').textContent = enabled ? `${freqMap[freq]}, pukul ${times} WIB` : 'Nonaktif';

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
        show_greeting: document.getElementById('prefShowGreeting').checked,
        holidays: cfgHolidays
    };

    const fd = new FormData();
    fd.append('emails', JSON.stringify(cfgEmails));
    if (document.getElementById('cfgDigest').checked) fd.append('digest_enabled', '1');
    fd.append('digest_freq', document.getElementById('cfgDigestFreq').value);
    fd.append('digest_time', document.getElementById('cfgDigestTime').value);
    fd.append('digest_time_2', document.getElementById('cfgDigestTime2').value);
    fd.append('digest_time_3', document.getElementById('cfgDigestTime3').value);
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
        if (!json.success) {
            alert(json.message || 'Gagal mengirim email.');
        } else {
            showToast(json.message || 'Email terkirim.');
        }
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

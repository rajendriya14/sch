<?php
$loggedName = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Ivan Rajendriya';
$loggedUser = $_SESSION['username'] ?? 'ivanrajendriya';
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sempoa — Forecast &amp; Actual Calculation</title>
    <link rel="icon" type="image/svg+xml" href="scheduling-system/public/assets/img/sempoa-logo.svg">
    <link rel="icon" type="image/png" sizes="180x180" href="scheduling-system/public/assets/img/sempoa-apple-touch-icon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="scheduling-system/public/assets/img/sempoa-apple-touch-icon.png">
    <link rel="manifest" href="scheduling-system/public/assets/manifest-sempoa.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Sempoa">
    <meta name="theme-color" content="#F3EBDD">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Shantell+Sans:wght@700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --excel-green: #217346;
            --excel-green-dark: #185c37;
            --excel-green-soft: #eaf5ee;
            --excel-green-mid: #d3ebd9;
            --excel-green-border: #a7d7b8;

            --word-blue: #185ABD;
            --word-blue-dark: #124694;
            --word-blue-soft: #eaf1fb;
            --word-blue-mid: #d3e2f7;
            --word-blue-border: #9ec2ef;

            --ppt-red: #D24726;
            --ppt-red-dark: #a6341a;
            --ppt-red-soft: #fdf0ed;
            --ppt-red-mid: #fadad3;
            --ppt-red-border: #f1b2a3;

            --teams-purple: #6264A7;
            --teams-purple-dark: #464775;
            --teams-purple-soft: #f0f0f8;
            --teams-purple-mid: #e0e1f2;
            --teams-purple-border: #b8b9de;

            --accent: var(--teams-purple);
            --accent-dark: var(--teams-purple-dark);
            --accent-soft: var(--teams-purple-soft);
            --accent-mid: var(--teams-purple-mid);
            --accent-border: var(--teams-purple-border);

            --summary-navy: #0f172a;
            --shell-bg: #F3EBDD;
            --border-subtle: #E2D6C2;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--shell-bg);
            color: #111827;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
        }
        .app-shell { display: flex; min-height: 100vh; }
        .app-sidebar {
            width: 256px;
            flex-shrink: 0;
            background: var(--shell-bg);
            padding: 14px 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid var(--border-subtle);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .brand-row { display: flex; align-items: center; justify-content: space-between; padding: 4px 6px 12px; }
        .brand-left { display: flex; align-items: center; gap: 9px; text-decoration: none; color: #111827; font-weight: 700; font-size: 1.08rem; }
        .brand-logo-img { width: 32px; height: 32px; object-fit: contain; background: transparent; border-radius: 8px; }
        .brand-name-cute {
            font-family: 'Shantell Sans', 'Comic Sans MS', cursive, sans-serif;
            font-size: 1.32rem;
            font-weight: 800;
            letter-spacing: 0.01em;
            color: #1a1a1a;
        }
        .sidebar-search { position: relative; margin-bottom: 12px; }
        .sidebar-search input {
            width: 100%; background: #fff; border: 1px solid var(--border-subtle);
            border-radius: 8px; padding: 6px 36px 6px 30px; font-size: 0.82rem; outline: none;
        }
        .sidebar-search .bi-search { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.8rem; }
        .sidebar-search .kbd-hint {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            font-size: 0.68rem; color: #9ca3af; background: #f3f4f6; padding: 1px 5px; border-radius: 4px; border: 1px solid #e5e7eb;
        }
        .nav-section-lbl {
            font-size: 0.7rem; font-weight: 700; color: #9ca3af; text-transform: uppercase;
            letter-spacing: 0.04em; padding: 8px 8px 4px;
        }
        .side-nav-btn {
            width: 100%; border: none; background: transparent; text-align: left;
            padding: 7px 10px; border-radius: 8px; display: flex; align-items: center;
            gap: 9px; font-size: 0.84rem; font-weight: 500; color: #4b5563;
            cursor: pointer; margin-bottom: 2px; transition: all 0.12s ease;
        }
        .side-nav-btn:hover { background: #EAE0CE; color: #111827; }
        .side-nav-btn.active {
            background: #fff; color: var(--accent); font-weight: 600;
            box-shadow: 0 1px 2px rgba(0,0,0,0.06); border: 1px solid #e5e7eb;
        }
        .dept-row { display: flex; align-items: center; justify-content: space-between; padding: 4px 10px; font-size: 0.79rem; color: #4b5563; }
        .dept-dot { width: 9px; height: 9px; border-radius: 50%; border: 2.5px solid; display: inline-block; margin-right: 7px; }
        .sidebar-plan-box { background: #fefce8; border: 1px solid #fef08a; border-radius: 10px; padding: 10px 12px; margin: 12px 0; font-size: 0.75rem; }
        .sidebar-user { display: flex; align-items: center; gap: 9px; padding: 8px 6px 2px; border-top: 1px solid var(--border-subtle); }
        .user-avatar {
            width: 30px; height: 30px; border-radius: 50%; background: #111827; color: #fff;
            display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700;
        }

        /* Main White Canvas */
        .app-main-wrap { flex: 1; min-width: 0; padding: 10px 12px 12px 0; }
        .app-canvas {
            background: #fff; border: 1px solid var(--border-subtle); border-radius: 14px;
            min-height: calc(100vh - 22px); box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            display: flex; flex-direction: column; overflow: hidden;
        }
        .canvas-topbar {
            padding: 12px 20px; border-bottom: 1px solid #f1f5f9;
            display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px;
        }
        .breadcrumb-title { display: flex; align-items: center; gap: 8px; font-size: 0.88rem; color: #6b7280; }
        .breadcrumb-title strong { color: #111827; }
        .canvas-body { padding: 18px 20px 24px; flex: 1; }

        .app-canvas.theme-teams { --accent: var(--teams-purple); --accent-dark: var(--teams-purple-dark); --accent-soft: var(--teams-purple-soft); --accent-mid: var(--teams-purple-mid); --accent-border: var(--teams-purple-border); }
        .app-canvas.theme-word { --accent: var(--word-blue); --accent-dark: var(--word-blue-dark); --accent-soft: var(--word-blue-soft); --accent-mid: var(--word-blue-mid); --accent-border: var(--word-blue-border); }
        .app-canvas.theme-ppt { --accent: var(--ppt-red); --accent-dark: var(--ppt-red-dark); --accent-soft: var(--ppt-red-soft); --accent-mid: var(--ppt-red-mid); --accent-border: var(--ppt-red-border); }
        .app-canvas.theme-excel { --accent: var(--excel-green); --accent-dark: var(--excel-green-dark); --accent-soft: var(--excel-green-soft); --accent-mid: var(--excel-green-mid); --accent-border: var(--excel-green-border); }

        /* 4 Separate KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 16px; }
        @media (max-width: 1100px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .kpi-grid { grid-template-columns: 1fr; } }
        .kpi-card-modern {
            border: 1px solid var(--border-subtle); border-radius: 12px; padding: 14px 16px 12px;
            background: #fff; display: flex; flex-direction: column; justify-content: space-between;
        }
        .kpi-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .kpi-icon-sq {
            width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e5e7eb;
            background: #f9fafb; display: inline-flex; align-items: center; justify-content: center;
            color: #374151; font-size: 0.95rem; flex-shrink: 0;
        }
        .kpi-title { font-size: 0.85rem; font-weight: 700; color: #111827; line-height: 1.2; }
        .kpi-sub-head { font-size: 0.72rem; color: #9ca3af; }
        .kpi-main-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .kpi-big-val { font-size: 1.35rem; font-weight: 700; color: #111827; font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }
        .kpi-delta-pill { font-size: 0.72rem; font-weight: 600; padding: 2px 8px; border-radius: 999px; }
        .kpi-delta-pill.up { background: #dcfce7; color: #15803d; }
        .kpi-delta-pill.down { background: #fee2e2; color: #b91c1c; }
        .kpi-delta-pill.neutral { background: #f3f4f6; color: #4b5563; }
        .kpi-note { font-size: 0.74rem; color: #6b7280; margin-top: 2px; }
        .kpi-viz { margin: 10px 0 6px; height: 34px; display: flex; align-items: flex-end; gap: 3px; }
        .kpi-bar-seg { flex: 1; border-radius: 3px 3px 2px 2px; min-height: 4px; }
        .kpi-viz-labels { display: flex; justify-content: space-between; font-size: 0.68rem; color: #9ca3af; margin-bottom: 8px; }
        .kpi-foot { border-top: 1px solid #f1f5f9; padding-top: 8px; display: flex; justify-content: space-between; font-size: 0.74rem; color: #6b7280; }
        .kpi-foot strong { color: #111827; font-variant-numeric: tabular-nums; }

        /* Middle Analytics */
        .dash-main-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr); gap: 16px; margin-bottom: 18px; }
        @media (max-width: 992px) { .dash-main-grid { grid-template-columns: 1fr; } }
        .dash-panel { border: 1px solid var(--border-subtle); border-radius: 12px; background: #fff; display: flex; flex-direction: column; overflow: hidden; }
        .dash-panel-head { padding: 13px 16px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .stacked-chart-area { display: flex; align-items: flex-end; justify-content: space-between; gap: 8px; height: 200px; padding: 20px 14px 0; border-bottom: 2px solid #e5e7eb; }
        .stacked-col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; min-width: 24px; }
        .stacked-total-top { font-size: 0.67rem; font-weight: 600; color: #4b5563; margin-bottom: 4px; }
        .stacked-bar-stack { width: 100%; max-width: 40px; display: flex; flex-direction: column-reverse; border-radius: 5px 5px 0 0; overflow: hidden; }
        .sb-seg { width: 100%; }
        .sb-main { background: #14b8a6; }
        .sb-add { background: #f59e0b; }
        .sb-sav { background: #8b5cf6; }
        .sb-net { background: #38bdf8; }
        .stacked-x-lbl { font-size: 0.72rem; color: #6b7280; margin-top: 6px; font-weight: 600; }
        .chart-legend { display: flex; justify-content: center; flex-wrap: wrap; gap: 14px; padding: 10px 14px 12px; font-size: 0.75rem; color: #4b5563; }
        .legend-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .margin-bar-row { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; margin-bottom: 9px; }
        .margin-bar-track { flex: 1; height: 6px; background: #f1f5f9; border-radius: 999px; overflow: hidden; }
        .margin-bar-fill { height: 100%; border-radius: 999px; }

        /* Annual Sheet Tabs */
        .annual-tabs-bar { display: flex; gap: 6px; border-bottom: 1px solid #e5e7eb; margin-bottom: 14px; flex-wrap: wrap; justify-content: space-between; align-items: center; }
        .annual-tabs-left { display: flex; gap: 6px; flex-wrap: wrap; }
        .annual-tab-btn {
            border: 1px solid transparent; border-bottom: 3px solid transparent; background: transparent;
            padding: 9px 18px; font-size: 0.88rem; font-weight: 600; color: #6b7280; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; border-radius: 8px 8px 0 0; margin-bottom: -1px; transition: all 0.15s ease;
        }
        .annual-tab-btn[data-sheet-tab="income"]:hover { color: var(--word-blue); background: var(--word-blue-soft); }
        .annual-tab-btn[data-sheet-tab="expenses"]:hover { color: var(--ppt-red); background: var(--ppt-red-soft); }
        .annual-tab-btn[data-sheet-tab="savings"]:hover { color: var(--excel-green); background: var(--excel-green-soft); }
        .annual-tab-btn[data-sheet-tab="income"].active { color: #fff; background: var(--word-blue); border-color: var(--word-blue); border-bottom-color: var(--word-blue-dark); }
        .annual-tab-btn[data-sheet-tab="expenses"].active { color: #fff; background: var(--ppt-red); border-color: var(--ppt-red); border-bottom-color: var(--ppt-red-dark); }
        .annual-tab-btn[data-sheet-tab="savings"].active { color: #fff; background: var(--excel-green); border-color: var(--excel-green); border-bottom-color: var(--excel-green-dark); }

        /* Classic Finance / ERP Grid (Dynamics NAV / Business Central style) */
        .sheet-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; font-size: 0.79rem; }
        .sheet-table thead th {
            padding: 6px 8px; font-weight: 600; font-size: 0.74rem; color: #0369a1; background: #fafbfc;
            border-bottom: 2px solid var(--accent); border-right: 1px solid #e5e7eb; white-space: nowrap; text-align: right;
        }
        .sheet-table thead th.col-item, .sheet-table tbody td.col-item {
            text-align: left; position: sticky; left: 0; background: #fff; z-index: 2;
            min-width: 220px; max-width: 260px; padding-left: 22px; border-right: 1px solid #cbd5e1;
            box-shadow: 2px 0 4px rgba(0,0,0,0.03);
        }
        .sheet-table thead th.col-item { background: #fafbfc; }
        .sheet-table tbody td {
            padding: 5px 8px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #edf2f7;
            text-align: right; white-space: nowrap; vertical-align: middle;
        }
        .sheet-table tbody tr.item-row:hover td,
        .sheet-table tbody tr.item-row:hover td.col-item { background-color: #eef6fb !important; }
        .sheet-table tbody tr.item-row td.col-item::before {
            content: '→'; position: absolute; left: 7px; color: #0284c7; font-weight: 700; opacity: 0; transition: opacity 0.1s;
        }
        .sheet-table tbody tr.item-row:hover td.col-item::before { opacity: 1; }
        .sheet-table tr.section-row td, .sheet-table tr.section-row td.col-item {
            background: #f1f5f9; padding: 5px 8px; text-align: left; border-top: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1;
        }
        .sheet-table tr.section-row td.col-item { padding-left: 12px; }
        .section-title-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--accent-dark); }
        .section-add-controls { display: inline-flex; align-items: center; gap: 6px; }
        .section-add-select { font-size: 0.76rem; padding: 2px 24px 2px 8px; height: 26px; min-width: 190px; }
        .section-custom-input { font-size: 0.76rem; padding: 2px 8px; height: 26px; width: 160px; }
        .btn-accent {
            background-color: var(--accent); border-color: var(--accent); color: #fff;
            font-size: 0.75rem; padding: 2px 10px; height: 26px; display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-accent:hover { background-color: var(--accent-dark); border-color: var(--accent-dark); color: #fff; }
        .btn-manage-list { font-size: 0.75rem; padding: 2px 9px; height: 26px; display: inline-flex; align-items: center; gap: 4px; }
        .sheet-table tr.subtotal-row td, .sheet-table tr.subtotal-row td.col-item {
            background: var(--accent-soft); color: var(--accent-dark); font-weight: 700;
            border-top: 1px solid var(--accent-border); border-bottom: 1px solid var(--accent-border);
        }
        .sheet-table tr.grandtotal-row td, .sheet-table tr.grandtotal-row td.col-item {
            background: var(--accent-mid); color: #111827; font-weight: 700;
            border-top: 2px solid var(--accent); border-bottom: 2px solid var(--accent);
        }
        .editable-cell { cursor: pointer; min-width: 98px; }
        .editable-cell:hover { background-color: #dff2f8 !important; outline: 1px solid #0284c7; outline-offset: -1px; }
        .editable-cell.is-zero { color: #9ca3af; }
        .cell-inline-input {
            width: 92px; text-align: right; font-size: 0.79rem; padding: 1px 5px;
            border: 1px solid #0284c7; border-radius: 2px; outline: none; background: #fff;
        }
        .col-year-total { background: #fafafa; font-weight: 600; border-left: 1px solid #cbd5e1; }
        .row-actions { opacity: 0.35; transition: opacity 0.15s; }
        .item-row:hover .row-actions { opacity: 1; }

        /* Shadow Forecast + Gap inside Actual Cell */
        .shadow-fcst { display: block; font-size: 0.66rem; color: #9ca3af; line-height: 1.1; margin-top: 1px; }
        .shadow-gap { display: block; font-size: 0.66rem; font-weight: 600; line-height: 1.1; }
        .gap-good { color: #15803d; }
        .gap-bad { color: #b91c1c; }
        .gap-zero { color: #9ca3af; }

        /* Summary Classic Finance Table */
        .summary-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; font-size: 0.79rem; }
        .summary-table thead th {
            background: #fafbfc; color: #0369a1; padding: 6px 8px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e5e7eb;
            font-weight: 700; font-size: 0.73rem; text-align: right; white-space: nowrap;
        }
        .summary-table thead th.col-det {
            text-align: left; position: sticky; left: 0; z-index: 3; background: #fafbfc; min-width: 220px; padding-left: 14px; border-right: 1px solid #cbd5e1;
        }
        .summary-table tbody td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #edf2f7; text-align: right; white-space: nowrap; }
        .summary-table tbody td.col-det {
            text-align: left; position: sticky; left: 0; z-index: 2; background: #fff; padding-left: 14px; border-right: 1px solid #cbd5e1; box-shadow: 2px 0 4px rgba(0,0,0,0.03);
        }
        .summary-table tbody tr:hover td, .summary-table tbody tr:hover td.col-det { background-color: #eef6fb; }
        .summary-group-row td, .summary-group-row td.col-det { background: #f1f5f9; font-weight: 700; color: #0f172a; cursor: pointer; }
        .summary-child-row td.col-det { padding-left: 34px; color: #475569; }
        .summary-total-cost td, .summary-total-cost td.col-det { background: #ffe4e6; color: #9f1239; font-weight: 700; }
        .summary-saving-row td, .summary-saving-row td.col-det { background: #eff6ff; color: #1d4ed8; font-weight: 700; }
        .summary-profit-row td, .summary-profit-row td.col-det { background: #dcfce7; color: #14532d; font-weight: 700; }
        .summary-pct-row td, .summary-pct-row td.col-det { background: #bbf7d0; color: #14532d; font-weight: 700; }
        .count-pill { font-size: 0.68rem; background: #e2e8f0; color: #475569; padding: 1px 6px; border-radius: 999px; margin-left: 6px; }

        /* Classic Finance ERP styling for Rate Card & Ledger tables */
        .app-canvas .table { font-size: 0.79rem; font-variant-numeric: tabular-nums; }
        .app-canvas .table thead th {
            background: #fafbfc; color: #0369a1; font-weight: 600; font-size: 0.74rem;
            border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e5e7eb; padding: 6px 8px;
        }
        .app-canvas .table tbody td { padding: 5px 8px; border-right: 1px solid #edf2f7; border-bottom: 1px solid #e2e8f0; }
        .app-canvas .table-hover tbody tr:hover td { background-color: #eef6fb; }

        /* Mobile & Tablet Responsive Drawer + Table Tuning */
        .mobile-menu-btn {
            display: none;
            border: 1px solid var(--border-subtle);
            background: #fff;
            border-radius: 8px;
            padding: 4px 9px;
            font-size: 1.05rem;
            color: #111827;
            cursor: pointer;
        }
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 1040;
        }
        .sidebar-backdrop.show { display: block; }

        @media (max-width: 991px) {
            .mobile-menu-btn { display: inline-flex; align-items: center; justify-content: center; }
            .app-sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                bottom: 0;
                width: 268px;
                height: 100vh;
                z-index: 1050;
                transition: left 0.22s ease;
                box-shadow: 4px 0 20px rgba(0,0,0,0.12);
            }
            .app-sidebar.open { left: 0; }
            .app-main-wrap { padding: 8px; }
            .app-canvas { min-height: calc(100vh - 16px); border-radius: 12px; }
            .canvas-topbar { padding: 10px 14px; }
            .canvas-body { padding: 14px; }
            .stacked-chart-area { overflow-x: auto; gap: 6px; padding: 18px 8px 0; }
            .stacked-col { min-width: 32px; }
        }

        @media (max-width: 576px) {
            .canvas-topbar { padding: 10px 12px; gap: 8px; }
            .canvas-body { padding: 12px; }
            .breadcrumb-title { font-size: 0.8rem; gap: 5px; }
            .sheet-table thead th.col-item, .sheet-table tbody td.col-item,
            .summary-table thead th.col-det, .summary-table tbody td.col-det {
                min-width: 132px;
                max-width: 150px;
                padding-left: 10px;
                font-size: 0.76rem;
                white-space: normal;
            }
            .sheet-table tbody td, .summary-table tbody td {
                padding: 6px 8px;
                font-size: 0.76rem;
            }
            .editable-cell { min-width: 82px; }
            .section-add-controls { flex-wrap: wrap; }
            .section-add-select { min-width: 150px; flex: 1 1 150px; }
            .annual-tab-btn { padding: 7px 12px; font-size: 0.82rem; }
            .kpi-big-val { font-size: 1.2rem; }
        }
        /* Rate Card UI & Slide-over Drawer */
        .rc-seg-wrap {
            display: grid; grid-template-columns: 1fr 1fr; background: #f3f4f6;
            padding: 3px; border-radius: 10px; border: 1px solid #e5e7eb;
        }
        .scenario-pill-wrap {
            display: inline-flex; background: #f3f4f6; padding: 3px; border-radius: 999px;
            border: 1px solid #e5e7eb; gap: 2px;
        }
        .scenario-pill-btn {
            border: none; background: transparent; padding: 4px 14px; font-size: 0.78rem;
            font-weight: 600; color: #6b7280; border-radius: 999px; cursor: pointer; transition: all 0.15s;
        }
        .scenario-pill-btn.active {
            background: #fff; color: #111827; box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .rc-seg-btn {
            border: none; background: transparent; padding: 6px 10px; font-size: 0.78rem;
            font-weight: 600; color: #6b7280; border-radius: 8px; cursor: pointer; transition: all 0.15s;
        }
        .rc-seg-btn.active { background: #fff; color: #111827; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
        .rc-month-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 8px; }
        .rc-year-lbl {
            width: 52px; flex-shrink: 0; font-size: 0.78rem; font-weight: 700; color: #111827;
            line-height: 1.2; cursor: pointer; border-radius: 6px; padding: 3px 5px; transition: background 0.12s;
        }
        .rc-year-lbl:hover { background: #fee2e2; color: #9f1239; }
        .rc-year-sub { font-size: 0.68rem; font-weight: 400; color: #9ca3af; }
        .rc-months-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 5px; flex: 1; user-select: none; }
        @media (min-width: 576px) { .rc-months-grid { grid-template-columns: repeat(12, 1fr); } }
        .rc-m-pill {
            position: relative; border: 1px solid #e5e7eb; background: #fff; color: #374151;
            border-radius: 7px; padding: 5px 2px; font-size: 0.74rem; font-weight: 600;
            text-align: center; cursor: pointer; transition: all 0.1s;
        }
        .rc-m-pill:hover { border-color: #9ca3af; background: #f9fafb; }
        .rc-m-pill.selected { background: #9f1239; color: #fff; border-color: #9f1239; }
        .rc-m-pill.has-own::after {
            content: ''; position: absolute; top: 3px; right: 3px; width: 5px; height: 5px;
            border-radius: 50%; background: #f59e0b;
        }
        .rc-m-pill.selected.has-own::after { background: #fde68a; }
        .rc-preview-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        @media (max-width: 576px) { .rc-preview-grid { grid-template-columns: 1fr; } }
        .rc-preview-box { border-radius: 10px; padding: 10px 12px; font-size: 0.78rem; }
        .rc-preview-changes { background: #fff1f2; border: 1px solid #fecdd3; }
        .rc-preview-unchanged { background: #f9fafb; border: 1px solid #e5e7eb; }
        .sub-nav-group { padding-left: 18px; margin-bottom: 4px; }
        .sub-nav-btn {
            width: 100%; border: none; background: transparent; text-align: left;
            padding: 5px 10px; border-radius: 7px; display: flex; align-items: center;
            gap: 8px; font-size: 0.8rem; font-weight: 500; color: #6b7280; cursor: pointer; margin-bottom: 1px;
        }
        .sub-nav-btn:hover { background: #EAE0CE; color: #111827; }
        .sub-nav-btn.active { background: #fff; color: var(--accent); font-weight: 600; border: 1px solid #e5e7eb; }

        @media print {
            .app-sidebar, .sidebar-backdrop, .canvas-topbar, .no-print { display: none !important; }
            .app-main-wrap { padding: 0 !important; }
            .app-canvas { border: none !important; box-shadow: none !important; }
            .canvas-body { padding: 0 !important; }
            .card { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-shell">
        <!-- Left Sidebar -->
        <aside class="app-sidebar" id="appSidebar">
            <div>
                <div class="brand-row">
                    <a href="#" class="brand-left">
                        <img src="scheduling-system/public/assets/img/sempoa-logo.svg" alt="Sempoa" class="brand-logo-img">
                        <span class="brand-name-cute">Sempoa</span>
                    </a>
                    <button type="button" class="btn btn-sm btn-link text-muted p-0" id="resetDemoBtn" title="Reset Data Contoh">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>

                <div class="sidebar-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="globalSearchInput" placeholder="Cari item...">
                    <span class="kbd-hint">⌘K</span>
                </div>

                <div class="nav-section-lbl">Workspace</div>
                <button type="button" class="side-nav-btn active" data-nav="forecast">
                    <i class="bi bi-calendar-check" style="color:var(--teams-purple);"></i>
                    <span>Forecast Calculation</span>
                </button>
                <button type="button" class="side-nav-btn" data-nav="actual">
                    <i class="bi bi-check2-square" style="color:var(--word-blue);"></i>
                    <span>Actual Calculation</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-auto" style="font-size:0.65rem;">vs Fcst</span>
                </button>
                <button type="button" class="side-nav-btn" data-nav="ratecard">
                    <i class="bi bi-credit-card-2-front" style="color:#9f1239;"></i>
                    <span>Rate Card</span>
                    <i class="bi bi-chevron-down ms-auto small text-muted"></i>
                </button>
                <div class="sub-nav-group" id="rateCardSubMenu">
                    <button type="button" class="sub-nav-btn" data-rc-sub="income">
                        <i class="bi bi-arrow-down-circle" style="color:var(--word-blue);"></i><span>Income</span>
                    </button>
                    <button type="button" class="sub-nav-btn" data-rc-sub="expense">
                        <i class="bi bi-cart-dash" style="color:var(--ppt-red);"></i><span>Expense</span>
                    </button>
                    <button type="button" class="sub-nav-btn" data-rc-sub="savings">
                        <i class="bi bi-piggy-bank" style="color:var(--excel-green);"></i><span>Saving</span>
                    </button>
                </div>
                <button type="button" class="side-nav-btn" data-nav="ledger">
                    <i class="bi bi-journal-text" style="color:#0f172a;"></i>
                    <span>Ledger</span>
                    <span class="badge bg-light text-secondary border ms-auto" style="font-size:0.65rem;">Per Bulan</span>
                </button>
                <button type="button" class="side-nav-btn" data-nav="settings">
                    <i class="bi bi-gear" style="color:#4b5563;"></i>
                    <span>Settings</span>
                </button>

                <div class="nav-section-lbl mt-3">Ringkasan (<span id="sidebarModeLabel">Forecast</span>)</div>
                <div id="sidebarDeptSummary"></div>
            </div>

            <div>
                <div class="sidebar-plan-box" id="sidebarSavingGoal"></div>
                <div class="d-flex gap-1 mb-2">
                    <a href="index.php?page=portal" class="btn btn-sm btn-outline-secondary w-100" style="font-size:0.75rem;">
                        <i class="bi bi-grid-1x2"></i> Portal
                    </a>
                    <a href="index.php?page=ffr&action=items" class="btn btn-sm btn-outline-secondary w-100" style="font-size:0.75rem;">
                        <i class="bi bi-list-check"></i> FFR Lama
                    </a>
                </div>
                <div class="sidebar-user">
                    <div class="user-avatar" id="sidebarAvatar">IR</div>
                    <div style="line-height:1.2;">
                        <div class="fw-semibold" id="sidebarUserName" style="font-size:0.82rem;"><?= htmlspecialchars($loggedName) ?></div>
                        <div class="text-muted" id="sidebarUserHandle" style="font-size:0.72rem;">@<?= htmlspecialchars($loggedUser) ?></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Rounded White Canvas -->
        <div class="app-main-wrap">
            <div class="app-canvas theme-teams" id="appCanvas">
                <div class="canvas-topbar">
                    <div class="breadcrumb-title">
                        <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Buka menu">
                            <i class="bi bi-list"></i>
                        </button>
                        <i class="bi bi-house d-none d-sm-inline"></i>
                        <span class="d-none d-sm-inline">Sempoa</span>
                        <span class="d-none d-sm-inline">—</span>
                        <span id="topModeBadge" class="badge bg-secondary-subtle text-dark border">Forecast</span>
                        <strong id="topBreadcrumbCurrent">Dashboard</strong>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="input-group input-group-sm" style="width:auto;">
                            <span class="input-group-text bg-white"><i class="bi bi-calendar3"></i></span>
                            <select class="form-select form-select-sm" id="yearSelect">
                                <option value="2026" selected>Jan – Des 2026</option>
                                <option value="2027">Jan – Des 2027</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="topResetBtn">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                        <button type="button" class="btn btn-sm btn-dark" id="topExportBtn">
                            <i class="bi bi-download"></i> Export Excel
                        </button>
                        <div class="dropdown">
                            <button class="btn btn-sm dropdown-toggle d-inline-flex align-items-center gap-2" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false"
                                    style="background:#fff;border:1px solid #e5e7eb;border-radius:999px;padding:4px 12px 4px 8px;color:#111827;font-weight:500;">
                                <i class="bi bi-person-circle" style="color:#217346;font-size:1.05rem;"></i>
                                <span id="topUserName"><?= htmlspecialchars($loggedName) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:0.84rem;">
                                <li><span class="dropdown-item-text small text-muted" id="topUserHandle">@<?= htmlspecialchars($loggedUser) ?></span></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="index.php?page=portal"><i class="bi bi-grid-1x2 me-2"></i>Portal</a></li>
                                <li><a class="dropdown-item" href="index.php?page=auth&action=changePassword"><i class="bi bi-key me-2"></i>Ganti Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="index.php?page=auth&action=logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="canvas-body" id="canvasBody"></div>
            </div>
        </div>
    </div>

    <!-- Offcanvas Drawer: Tambah / Edit Rate Card -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="rateCardDrawer" style="width: 540px; max-width: 100vw;">
        <div class="offcanvas-header border-bottom py-3">
            <div>
                <h6 class="offcanvas-title fw-bold mb-0" id="rcDrawerTitle">Edit rate card</h6>
                <div class="small text-muted" id="rcDrawerSub">Sempoa · Forecast &amp; Actual</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <form id="rateCardForm">
                <input type="hidden" id="rcEditId" value="">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-bold small">Detail Item</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" style="font-size:0.7rem;">
                        <i class="bi bi-layers"></i> All months and years
                    </span>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small mb-1">Kelompok</label>
                        <select class="form-select form-select-sm" id="rcSection"></select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1">Nama Item <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="rcName" required placeholder="mis. Gaji Utama, Listrik, Dana Darurat">
                    </div>
                    <div class="col-4">
                        <label class="form-label small mb-1">Target Data</label>
                        <select class="form-select form-select-sm" id="rcTargetMode">
                            <option value="forecast">Forecast</option>
                            <option value="actual">Actual</option>
                            <option value="both">Forecast &amp; Actual</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small mb-1">Frekuensi</label>
                        <select class="form-select form-select-sm" id="rcFreq">
                            <option value="monthly">Monthly (×1)</option>
                            <option value="biweekly">Biweekly (×2)</option>
                            <option value="weekly">Weekly (×4)</option>
                            <option value="once">Once / Tahunan</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small mb-1">Qty</label>
                        <input type="number" class="form-control form-control-sm" id="rcQty" min="1" step="1" value="1">
                    </div>
                </div>

                <div class="border-top pt-3 mb-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="fw-bold small">Rate</span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill" id="rcScopeBadge" style="font-size:0.7rem;">
                            <i class="bi bi-calendar-event"></i> Pilih bulan di bawah
                        </span>
                    </div>
                    <label class="form-label small text-muted mb-1">Apa yang ingin diubah?</label>
                    <div class="rc-seg-wrap mb-3">
                        <button type="button" class="rc-seg-btn active" data-rc-editmode="selected">Selected months' own rate</button>
                        <button type="button" class="rc-seg-btn" data-rc-editmode="base">Base rate (semua bulan)</button>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label small mb-1">Rate amount (Rp)</label>
                            <input type="number" class="form-control form-control-sm text-end fw-semibold" id="rcAmount" min="0" step="1000" value="0">
                        </div>
                        <div class="col-5">
                            <label class="form-label small mb-1">Mata Uang</label>
                            <input type="text" class="form-control form-control-sm bg-light" value="IDR (Rp)" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Penyesuaian / Fee %</label>
                            <input type="number" class="form-control form-control-sm text-end" id="rcFeePct" step="0.1" placeholder="e.g. 0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Total per Bulan</label>
                            <div class="form-control form-control-sm bg-light text-end fw-bold" id="rcTotalPreview">Rp 0</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold mb-0">Apply to</label>
                        <span class="small text-muted" id="rcSelectedCount">0 selected</span>
                    </div>
                    <div id="rcApplyGrid" class="mb-2"></div>
                    <div class="small text-muted mb-3" style="font-size:0.72rem;">
                        Klik bulan (atau klik &amp; geser) untuk memilih beberapa bulan. Titik kuning menandakan bulan tersebut sudah memiliki nominal.
                    </div>

                    <div class="rc-preview-grid mb-3">
                        <div class="rc-preview-box rc-preview-changes">
                            <div class="fw-bold text-danger mb-1" style="font-size:0.7rem;">CHANGES</div>
                            <div class="fw-semibold text-dark" id="rcChangesLabel">Belum ada bulan dipilih</div>
                            <div class="text-muted" id="rcChangesAmount">Rp 0</div>
                        </div>
                        <div class="rc-preview-box rc-preview-unchanged">
                            <div class="fw-bold text-muted mb-1" style="font-size:0.7rem;">UNCHANGED</div>
                            <div class="fw-semibold text-dark" id="rcUnchangedLabel">Bulan lainnya</div>
                            <div class="text-muted">Nilai sebelumnya tetap tersimpan</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top pt-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger px-3" id="rcSaveBtn">Save Rate Card</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Reset Bulanan & Tahunan -->
    <div class="modal fade" id="resetDataModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white py-2">
                    <h6 class="modal-title"><i class="bi bi-arrow-counterclockwise"></i> Reset Data Bulanan / Tahunan</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Reset Bulanan -->
                    <div class="border rounded p-3 mb-3 bg-light">
                        <div class="fw-bold small mb-2 text-dark"><i class="bi bi-calendar-month me-1"></i> Reset Bulanan (Nolkan Bulan Tertentu)</div>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label small mb-1">Tahun</label>
                                <select class="form-select form-select-sm" id="modalResetMonthYear"></select>
                            </div>
                            <div class="col-4">
                                <label class="form-label small mb-1">Bulan</label>
                                <select class="form-select form-select-sm" id="modalResetMonthIdx"></select>
                            </div>
                            <div class="col-4">
                                <label class="form-label small mb-1">Target</label>
                                <select class="form-select form-select-sm" id="modalResetMonthTarget">
                                    <option value="both">Forecast &amp; Actual</option>
                                    <option value="forecast">Forecast saja</option>
                                    <option value="actual">Actual saja</option>
                                </select>
                            </div>
                            <div class="col-12 mt-2">
                                <button type="button" class="btn btn-sm btn-danger w-100" id="btnExecResetMonth">
                                    <i class="bi bi-eraser"></i> Reset Bulan Terpilih
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Reset Tahunan -->
                    <div class="border rounded p-3 bg-light">
                        <div class="fw-bold small mb-2 text-dark"><i class="bi bi-calendar-range me-1"></i> Reset Tahunan (1 Tahun Penuh)</div>
                        <div class="row g-2">
                            <div class="col-5">
                                <label class="form-label small mb-1">Tahun</label>
                                <select class="form-select form-select-sm" id="modalResetYearSelect"></select>
                            </div>
                            <div class="col-7">
                                <label class="form-label small mb-1">Mode Reset</label>
                                <select class="form-select form-select-sm" id="modalResetYearMode">
                                    <option value="both">Nolkan Forecast &amp; Actual (Jan–Des)</option>
                                    <option value="forecast">Nolkan Forecast saja (Jan–Des)</option>
                                    <option value="actual">Nolkan Actual saja (Jan–Des)</option>
                                    <option value="clear_items">Hapus semua baris item tahun ini</option>
                                </select>
                            </div>
                            <div class="col-12 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-danger w-100" id="btnExecResetYear">
                                    <i class="bi bi-trash"></i> Reset Tahun Terpilih
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Kelola List Dropdown -->
    <div class="modal fade" id="manageListModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white py-2">
                    <h6 class="modal-title" id="manageListTitle"><i class="bi bi-sliders"></i> Kelola Pilihan Dropdown</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addPresetForm" class="input-group input-group-sm mb-3">
                        <input type="text" class="form-control" id="newPresetInput" placeholder="Tambah pilihan baru ke dropdown..." required>
                        <button type="submit" class="btn btn-dark"><i class="bi bi-plus-lg"></i> Tambah ke List</button>
                    </form>
                    <div class="small text-muted mb-2">Daftar pilihan di dropdown saat ini:</div>
                    <ul class="list-group list-group-flush border rounded" id="managePresetList" style="max-height: 260px; overflow-y: auto;"></ul>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Selesai</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
    <script>
        (function () {
            const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const MONTH_NAMES = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            const STORAGE_KEY = 'celengan_fcst_actual_v1';

            const DEFAULT_PRESETS = {
                income: ['Dividen & Bunga Bank', 'Cashback / Refund', 'Uang Lembur', 'Penjualan Barang', 'Hasil Sewa'],
                main: ['Kos / Kontrakan', 'BPJS & Kesehatan', 'Gas & Air Galon', 'Pendidikan / Sekolah', 'Pulsa & Paket Data', 'Perawatan Bayi / Anak', 'Iuran Keamanan & Sampah'],
                additional: ['Service Kendaraan', 'Kondangan & Hadiah', 'Belanja Pakaian', 'Liburan Keluarga', 'Perbaikan Rumah', 'Biaya Tak Terduga'],
                savings: ['Deposito', 'Reksa Dana Pasar Uang', 'SBN / Obligasi', 'Tabungan Pendidikan', 'Dana Liburan', 'Dana Renovasi Rumah']
            };

            const DEFAULT_YEARS = {
                '2026': {
                    income: [
                        { id: 201, name: 'Gaji Utama', forecast: Array(12).fill(12000000), actual: [12000000,12000000,12000000,12000000,12000000,12000000,12000000,12000000,12000000,12000000,12000000,0] },
                        { id: 202, name: 'Gaji Pasangan', forecast: Array(12).fill(6500000), actual: [6500000,6500000,6500000,6500000,6500000,6500000,6500000,6500000,6500000,6500000,6500000,0] },
                        { id: 203, name: 'Bonus / THR', forecast: [0,0,12000000,0,0,0,0,0,0,0,0,5000000], actual: [0,0,13500000,0,0,0,0,0,0,0,0,0] },
                        { id: 204, name: 'Side Income / Freelance', forecast: [1500000,0,2000000,1000000,1500000,0,2500000,1000000,1500000,2000000,1500000,3000000], actual: [1800000,500000,1500000,1200000,1000000,0,2500000,800000,1700000,2200000,1250000,0] }
                    ],
                    main: [
                        { id: 1, name: 'Makan & Kebutuhan Dapur', forecast: Array(12).fill(3500000), actual: [3450000,3600000,3500000,3750000,3400000,3550000,3620000,3500000,3480000,3650000,3400000,0] },
                        { id: 2, name: 'Listrik, Air & Internet', forecast: [950000,950000,980000,950000,950000,1020000,950000,950000,950000,950000,950000,950000], actual: [940000,960000,990000,930000,950000,1080000,960000,940000,950000,980000,920000,0] },
                        { id: 3, name: 'Transport & Bensin', forecast: [800000,800000,800000,850000,800000,800000,800000,800000,800000,800000,800000,800000], actual: [780000,820000,790000,900000,810000,770000,830000,790000,800000,840000,760000,0] },
                        { id: 4, name: 'Cicilan Wajib', forecast: Array(12).fill(2500000), actual: [2500000,2500000,2500000,2500000,2500000,2500000,2500000,2500000,2500000,2500000,2500000,0] }
                    ],
                    additional: [
                        { id: 5, name: 'Pajak Motor & STNK', forecast: [0,0,650000,0,0,0,0,0,0,0,0,0], actual: [0,0,675000,0,0,0,0,0,0,0,0,0] },
                        { id: 6, name: 'Servis AC Berkala', forecast: [0,300000,0,0,0,300000,0,0,0,300000,0,0], actual: [0,280000,0,0,0,350000,0,0,0,300000,0,0] },
                        { id: 7, name: 'PBB Rumah', forecast: [0,0,0,0,0,0,0,850000,0,0,0,0], actual: [0,0,0,0,0,0,0,850000,0,0,0,0] },
                        { id: 8, name: 'Hiburan & Makan Luar', forecast: [500000,500000,500000,750000,500000,500000,500000,500000,500000,500000,500000,1200000], actual: [450000,620000,480000,820000,490000,530000,600000,450000,510000,580000,420000,0] }
                    ],
                    savings: [
                        { id: 9, name: 'Dana Darurat', forecast: Array(12).fill(1500000), actual: [1500000,1500000,1500000,1500000,1500000,1500000,1500000,1500000,1500000,1500000,1500000,0] },
                        { id: 10, name: 'Tabungan Qurban / Hari Raya', forecast: Array(12).fill(300000), actual: [300000,300000,300000,300000,300000,300000,300000,300000,300000,300000,300000,0] },
                        { id: 11, name: 'Investasi Emas / Reksadana', forecast: Array(12).fill(1000000), actual: [1000000,800000,1500000,1000000,1000000,1000000,1200000,1000000,1000000,1000000,1000000,0] }
                    ]
                },
                '2027': {
                    income: [{ id: 301, name: 'Gaji Utama', forecast: Array(12).fill(12000000), actual: Array(12).fill(0) }],
                    main: [{ id: 302, name: 'Makan & Kebutuhan Dapur', forecast: Array(12).fill(3500000), actual: Array(12).fill(0) }],
                    additional: [],
                    savings: [{ id: 303, name: 'Dana Darurat', forecast: Array(12).fill(1500000), actual: Array(12).fill(0) }]
                }
            };

            const DEFAULT_SETTINGS = {
                appName: 'Sempoa',
                userName: <?= json_encode($loggedName) ?>,
                userHandle: <?= json_encode('@' . $loggedUser) ?>,
                savingTargetPct: 20,
                activeMonthIdx: 10
            };

            let state = loadState();
            if (!state.settings) state.settings = structuredClone(DEFAULT_SETTINGS);
            let currentYear = '2026';
            let currentNav = 'forecast'; // 'forecast' | 'actual' | 'ratecard' | 'ledger' | 'settings'
            let currentRateCardSub = 'income'; // 'income' | 'expense' | 'savings'
            let currentRcScenario = 'forecast'; // 'forecast' | 'actual'
            let currentSheetTab = 'expenses';
            let ledgerMonthIdx = 10;
            let searchQuery = '';
            let managingSection = 'main';
            let expandedGroups = { income: false, main: true, additional: false, savings: false };
            const manageModal = new bootstrap.Modal(document.getElementById('manageListModal'));
            const resetModal = new bootstrap.Modal(document.getElementById('resetDataModal'));
            const rateCardDrawer = new bootstrap.Offcanvas(document.getElementById('rateCardDrawer'));
            let rcEditMode = 'selected'; // 'selected' | 'base'
            let rcSelectedMonths = new Set(); // e.g. '2026-0'
            let rcIsDragging = false;
            let rcDragAdd = true;

            function loadState() {
                try {
                    const raw = localStorage.getItem(STORAGE_KEY);
                    if (raw) return JSON.parse(raw);
                } catch (e) {}
                return { years: structuredClone(DEFAULT_YEARS), presets: structuredClone(DEFAULT_PRESETS), settings: structuredClone(DEFAULT_SETTINGS) };
            }
            function saveState() { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); }
            function isActualMode() { return currentNav.startsWith('actual'); }
            function rp(num) {
                const n = Math.round(num || 0);
                return (n < 0 ? '-Rp ' : 'Rp ') + Math.abs(n).toLocaleString('id-ID');
            }
            function rpCompact(num) {
                const n = Math.abs(num || 0);
                const sign = num < 0 ? '-' : '';
                if (n >= 1000000000) return sign + 'Rp ' + (n / 1000000000).toFixed(1) + 'M';
                if (n >= 1000000) return sign + 'Rp ' + (n / 1000000).toFixed(1) + 'jt';
                if (n >= 1000) return sign + 'Rp ' + Math.round(n / 1000) + 'rb';
                return sign + 'Rp ' + n;
            }
            function esc(str) {
                return String(str || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
            }
            function sumSection(items, field) {
                const monthly = Array(12).fill(0);
                let total = 0;
                (items || []).forEach(item => {
                    const arr = item[field] || [];
                    for (let m = 0; m < 12; m++) {
                        const v = Number(arr[m] || 0);
                        monthly[m] += v;
                        total += v;
                    }
                });
                return { monthly, total };
            }

            function formatGapHtml(act, fcst, isExpense = false) {
                const gap = act - fcst;
                if (gap === 0) return `<span class="shadow-gap gap-zero">Gap: Rp 0</span>`;
                // For Income/Saving: positive gap is good. For Expense: negative gap (hemat) is good.
                const isGood = isExpense ? (gap < 0) : (gap > 0);
                const cls = isGood ? 'gap-good' : 'gap-bad';
                const sign = gap > 0 ? '+' : '';
                return `<span class="shadow-gap ${cls}">Gap: ${sign}${rpCompact(gap)}</span>`;
            }

            function buildSparklineSvg(values, color, activeIdx) {
                const max = Math.max(1, ...values.map(v => Math.abs(v)));
                const w = 220, h = 32;
                const pts = values.map((v, i) => {
                    const x = (i / 11) * w;
                    const y = h - 4 - ((Math.max(0, v) / max) * (h - 8));
                    return `${x.toFixed(1)},${y.toFixed(1)}`;
                });
                const activeX = ((activeIdx / 11) * w).toFixed(1);
                const activeY = (h - 4 - ((Math.max(0, values[activeIdx]) / max) * (h - 8))).toFixed(1);
                return `<svg viewBox="0 0 ${w} ${h}" width="100%" height="32" preserveAspectRatio="none"><polyline fill="none" stroke="${color}" stroke-width="2" points="${pts.join(' ')}" /><circle cx="${activeX}" cy="${activeY}" r="3.5" fill="${color}" /></svg>`;
            }
            function buildMiniBarsHtml(values, activeColor, softColor, activeIdx) {
                const max = Math.max(1, ...values.map(v => Math.abs(v)));
                return values.map((v, i) => {
                    const pct = Math.max(12, Math.round((Math.max(0, v) / max) * 100));
                    const bg = i === activeIdx ? activeColor : softColor;
                    return `<div class="kpi-bar-seg" style="height:${pct}%;background:${bg};" title="${MONTHS[i]}: ${rp(v)}"></div>`;
                }).join('');
            }

            function renderSidebarStats(yearData) {
                const field = isActualMode() ? 'actual' : 'forecast';
                document.getElementById('sidebarModeLabel').textContent = isActualMode() ? 'Actual' : 'Forecast';
                const inc = sumSection(yearData.income, field);
                const main = sumSection(yearData.main, field);
                const add = sumSection(yearData.additional, field);
                const sav = sumSection(yearData.savings, field);

                document.getElementById('sidebarDeptSummary').innerHTML = `
                    <div class="dept-row"><span><span class="dept-dot" style="border-color:var(--word-blue)"></span>Income</span><strong>${rpCompact(inc.total)}</strong></div>
                    <div class="dept-row"><span><span class="dept-dot" style="border-color:var(--ppt-red)"></span>Main Exp</span><strong>${rpCompact(main.total)}</strong></div>
                    <div class="dept-row"><span><span class="dept-dot" style="border-color:#f59e0b"></span>Additional</span><strong>${rpCompact(add.total)}</strong></div>
                    <div class="dept-row"><span><span class="dept-dot" style="border-color:var(--excel-green)"></span>Saving</span><strong>${rpCompact(sav.total)}</strong></div>
                `;
                const savRate = inc.total > 0 ? Math.min(100, Math.round((sav.total / inc.total) * 100)) : 0;
                document.getElementById('sidebarSavingGoal').innerHTML = `
                    <div class="fw-bold text-dark mb-1">Target Saving (${isActualMode() ? 'Actual' : 'Forecast'})</div>
                    <div class="text-muted mb-2">${savRate}% dari income (${rpCompact(sav.total)})</div>
                    <div class="progress" style="height:6px;background:#fef08a;"><div class="progress-bar bg-success" style="width:${Math.min(100, savRate * 5)}%"></div></div>
                `;
            }

            function renderDashboardView(yearData, mode) {
                const isAct = mode === 'actual';
                const field = isAct ? 'actual' : 'forecast';

                const inc = sumSection(yearData.income, field);
                const main = sumSection(yearData.main, field);
                const add = sumSection(yearData.additional, field);
                const sav = sumSection(yearData.savings, field);
                const expMonthly = main.monthly.map((v, i) => v + add.monthly[i]);
                const expTotal = main.total + add.total;
                const netMonthly = inc.monthly.map((v, i) => v - expMonthly[i] - sav.monthly[i]);
                const netTotal = inc.total - expTotal - sav.total;

                // Shadow Forecast totals for Gap in Actual Dashboard
                const fInc = sumSection(yearData.income, 'forecast');
                const fMain = sumSection(yearData.main, 'forecast');
                const fAdd = sumSection(yearData.additional, 'forecast');
                const fSav = sumSection(yearData.savings, 'forecast');
                const fExpTotal = fMain.total + fAdd.total;
                const fNetTotal = fInc.total - fExpTotal - fSav.total;

                const curM = Number(state.settings?.activeMonthIdx ?? 10);
                const prevM = curM > 0 ? curM - 1 : 0;
                const savPct = inc.total > 0 ? ((sav.total / inc.total) * 100).toFixed(1) : '0.0';
                const netPct = inc.total > 0 ? ((netTotal / inc.total) * 100).toFixed(1) : '0.0';

                function kpiBadge(curVal, prevVal, actTot, fcstTot, inv = false) {
                    if (isAct) {
                        const gap = actTot - fcstTot;
                        if (gap === 0) return `<span class="kpi-delta-pill neutral">Gap Rp 0</span>`;
                        const good = inv ? (gap < 0) : (gap > 0);
                        return `<span class="kpi-delta-pill ${good ? 'up' : 'down'}">Gap ${gap > 0 ? '+' : ''}${rpCompact(gap)}</span>`;
                    }
                    const d = curVal - prevVal;
                    if (d === 0) return `<span class="kpi-delta-pill neutral">0% vs ${MONTHS[prevM]}</span>`;
                    const up = d > 0;
                    const cls = inv ? (up ? 'down' : 'up') : (up ? 'up' : 'down');
                    return `<span class="kpi-delta-pill ${cls}">${up ? '↗ +' : '↘ '}${rpCompact(d)}</span>`;
                }

                // Stacked Chart
                const maxBarVal = Math.max(1, ...MONTHS.map((_, i) => main.monthly[i] + add.monthly[i] + sav.monthly[i] + Math.max(0, netMonthly[i])));
                let barsHtml = '';
                MONTHS.forEach((m, i) => {
                    const hMain = Math.round((main.monthly[i] / maxBarVal) * 155);
                    const hAdd = Math.round((add.monthly[i] / maxBarVal) * 155);
                    const hSav = Math.round((sav.monthly[i] / maxBarVal) * 155);
                    const hNet = Math.round((Math.max(0, netMonthly[i]) / maxBarVal) * 155);
                    barsHtml += `
                        <div class="stacked-col" title="${m}: ${rp(inc.monthly[i])}">
                            <div class="stacked-total-top">${rpCompact(inc.monthly[i]).replace('Rp ', '')}</div>
                            <div class="stacked-bar-stack">
                                <div class="sb-seg sb-main" style="height:${hMain}px"></div>
                                <div class="sb-seg sb-add" style="height:${hAdd}px"></div>
                                <div class="sb-seg sb-sav" style="height:${hSav}px"></div>
                                <div class="sb-seg sb-net" style="height:${hNet}px"></div>
                            </div>
                            <div class="stacked-x-lbl">${m}</div>
                        </div>
                    `;
                });

                const pctMain = inc.total > 0 ? ((main.total / inc.total) * 100).toFixed(1) : '0.0';
                const pctAdd = inc.total > 0 ? ((add.total / inc.total) * 100).toFixed(1) : '0.0';
                const pctSav = inc.total > 0 ? ((sav.total / inc.total) * 100).toFixed(1) : '0.0';
                const pctNet = inc.total > 0 ? ((Math.max(0, netTotal) / inc.total) * 100).toFixed(1) : '0.0';
                const healthPct = (Number(pctSav) + Number(pctNet)).toFixed(1);

                // Summary Table
                function renderGroup(groupKey, label, items, sums, fSums, rowClass = 'summary-group-row') {
                    const isExp = expandedGroups[groupKey];
                    const chev = isExp ? 'bi-chevron-down' : 'bi-chevron-right';
                    let gHtml = `<tr class="${rowClass}" data-group="${groupKey}"><td class="col-det"><i class="bi ${chev} me-1"></i> ${esc(label)}<span class="count-pill">${(items || []).length} items</span></td>`;
                    for (let m = 0; m < 12; m++) gHtml += `<td>${rp(sums.monthly[m])}</td>`;
                    gHtml += `<td>${rp(sums.total)}</td>`;
                    if (isAct) {
                        const gap = sums.total - fSums.total;
                        gHtml += `<td>${rp(fSums.total)}</td><td class="${gap === 0 ? 'text-muted' : ((groupKey === 'main' || groupKey === 'additional') ? (gap < 0 ? 'text-success' : 'text-danger') : (gap > 0 ? 'text-success' : 'text-danger'))}">${gap > 0 ? '+' : ''}${rp(gap)}</td>`;
                    }
                    gHtml += `</tr>`;
                    if (isExp) {
                        (items || []).forEach(it => {
                            const arr = it[field] || [];
                            const fArr = it.forecast || [];
                            const rSum = arr.reduce((a, b) => a + Number(b || 0), 0);
                            const fSum = fArr.reduce((a, b) => a + Number(b || 0), 0);
                            const gSum = rSum - fSum;
                            gHtml += `<tr class="summary-child-row"><td class="col-det">${esc(it.name)}</td>`;
                            for (let m = 0; m < 12; m++) gHtml += `<td>${rp(arr[m])}</td>`;
                            gHtml += `<td>${rp(rSum)}</td>`;
                            if (isAct) gHtml += `<td class="text-muted">${rp(fSum)}</td><td>${gSum > 0 ? '+' : ''}${rp(gSum)}</td>`;
                            gHtml += `</tr>`;
                        });
                    }
                    return gHtml;
                }

                let sumHead = `<tr><th class="col-det">DETAILS</th>${MONTHS.map(m => `<th>${m}</th>`).join('')}<th>TOTAL</th>${isAct ? '<th>FORECAST</th><th>GAP</th>' : ''}</tr>`;
                let sumBody = renderGroup('income', 'Income', yearData.income, inc, fInc)
                    + renderGroup('main', 'Main Expenses', yearData.main, main, fMain)
                    + renderGroup('additional', 'Additional Expenses', yearData.additional, add, fAdd)
                    + `<tr class="summary-total-cost"><td class="col-det">Total Expenses (Main + Additional)</td>${expMonthly.map(v => `<td>${rp(v)}</td>`).join('')}<td>${rp(expTotal)}</td>${isAct ? `<td>${rp(fExpTotal)}</td><td>${(expTotal - fExpTotal) > 0 ? '+' : ''}${rp(expTotal - fExpTotal)}</td>` : ''}</tr>`
                    + renderGroup('savings', 'Saving', yearData.savings, sav, fSav, 'summary-group-row summary-saving-row')
                    + `<tr class="summary-profit-row"><td class="col-det">Sisa Kas (Income - Expenses - Saving)</td>${netMonthly.map(v => `<td>${rp(v)}</td>`).join('')}<td>${rp(netTotal)}</td>${isAct ? `<td>${rp(fNetTotal)}</td><td>${(netTotal - fNetTotal) > 0 ? '+' : ''}${rp(netTotal - fNetTotal)}</td>` : ''}</tr>`
                    + `<tr class="summary-pct-row"><td class="col-det">Sisa Kas %</td>${netMonthly.map((v, i) => `<td>${inc.monthly[i] > 0 ? ((v / inc.monthly[i]) * 100).toFixed(1) : '0.0'}%</td>`).join('')}<td>${netPct}%</td>${isAct ? `<td colspan="2"></td>` : ''}</tr>`;

                document.getElementById('canvasBody').innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h5 class="mb-0 fw-bold">Dashboard ${isAct ? 'Actual Calculation (vs Forecast)' : 'Forecast Calculation'} — ${currentYear}</h5>
                            <div class="small text-muted">${isAct ? 'Realisasi aktual dibandingkan dengan shadow angka Forecast & Gap-nya.' : 'Perencanaan & proyeksi keuangan keluarga selama 1 tahun.'}</div>
                        </div>
                        <span class="badge ${isAct ? 'bg-primary' : 'bg-dark'} px-3 py-2">${isAct ? 'Mode: Actual + Shadow Forecast' : 'Mode: Forecast'}</span>
                    </div>

                    <div class="kpi-grid">
                        <div class="kpi-card-modern">
                            <div>
                                <div class="kpi-head">
                                    <div class="kpi-icon-sq"><i class="bi bi-wallet2"></i></div>
                                    <div>
                                        <div class="kpi-title">Total Income</div>
                                        <div class="kpi-sub-head">${isAct ? 'Shadow Forecast: ' + rpCompact(fInc.total) : 'vs bulan lalu (' + MONTHS[prevM] + ')'}</div>
                                    </div>
                                </div>
                                <div class="kpi-main-row">
                                    <div class="kpi-big-val">${rpCompact(inc.total)}</div>
                                    ${kpiBadge(inc.monthly[curM], inc.monthly[prevM], inc.total, fInc.total)}
                                </div>
                                <div class="kpi-note">${MONTHS[curM]}: ${rp(inc.monthly[curM])}</div>
                            </div>
                            <div>
                                <div class="kpi-viz">${buildSparklineSvg(inc.monthly, '#185ABD', curM)}</div>
                                <div class="kpi-viz-labels"><span>Jan</span><span>${MONTHS[curM]}</span><span>Des</span></div>
                                <div class="kpi-foot">
                                    <span>${isAct ? 'Forecast' : 'Rata-rata'} <strong>${rpCompact(isAct ? fInc.total : inc.total / 12)}</strong></span>
                                    <span>Des <strong>${rpCompact(inc.monthly[11])}</strong></span>
                                </div>
                            </div>
                        </div>

                        <div class="kpi-card-modern">
                            <div>
                                <div class="kpi-head">
                                    <div class="kpi-icon-sq"><i class="bi bi-cart-dash"></i></div>
                                    <div>
                                        <div class="kpi-title">Total Expenses</div>
                                        <div class="kpi-sub-head">${isAct ? 'Shadow Forecast: ' + rpCompact(fExpTotal) : 'Main + Additional'}</div>
                                    </div>
                                </div>
                                <div class="kpi-main-row">
                                    <div class="kpi-big-val">${rpCompact(expTotal)}</div>
                                    ${kpiBadge(expMonthly[curM], expMonthly[prevM], expTotal, fExpTotal, true)}
                                </div>
                                <div class="kpi-note">${inc.total > 0 ? ((expTotal / inc.total) * 100).toFixed(1) : '0.0'}% dari total income</div>
                            </div>
                            <div>
                                <div class="kpi-viz">${buildSparklineSvg(expMonthly, '#D24726', curM)}</div>
                                <div class="kpi-viz-labels"><span>Jan</span><span>${MONTHS[curM]}</span><span>Des</span></div>
                                <div class="kpi-foot">
                                    <span>Main <strong>${rpCompact(main.total)}</strong></span>
                                    <span>Add <strong>${rpCompact(add.total)}</strong></span>
                                </div>
                            </div>
                        </div>

                        <div class="kpi-card-modern">
                            <div>
                                <div class="kpi-head">
                                    <div class="kpi-icon-sq"><i class="bi bi-piggy-bank"></i></div>
                                    <div>
                                        <div class="kpi-title">Total Saving</div>
                                        <div class="kpi-sub-head">${isAct ? 'Shadow Forecast: ' + rpCompact(fSav.total) : 'Saving rate ' + savPct + '%'}</div>
                                    </div>
                                </div>
                                <div class="kpi-main-row">
                                    <div class="kpi-big-val">${rpCompact(sav.total)}</div>
                                    ${kpiBadge(sav.monthly[curM], sav.monthly[prevM], sav.total, fSav.total)}
                                </div>
                                <div class="kpi-note">${MONTHS[curM]}: ${rp(sav.monthly[curM])}</div>
                            </div>
                            <div>
                                <div class="kpi-viz">${buildMiniBarsHtml(sav.monthly, '#8b5cf6', '#ddd6fe', curM)}</div>
                                <div class="kpi-viz-labels"><span>Jan</span><span>${MONTHS[curM]}</span><span>Des</span></div>
                                <div class="kpi-foot">
                                    <span>${isAct ? 'Forecast' : 'Rata-rata'} <strong>${rpCompact(isAct ? fSav.total : sav.total / 12)}</strong></span>
                                    <span>Rate <strong>${savPct}%</strong></span>
                                </div>
                            </div>
                        </div>

                        <div class="kpi-card-modern">
                            <div>
                                <div class="kpi-head">
                                    <div class="kpi-icon-sq"><i class="bi bi-graph-up-arrow"></i></div>
                                    <div>
                                        <div class="kpi-title">Sisa Kas (Net)</div>
                                        <div class="kpi-sub-head">${isAct ? 'Shadow Forecast: ' + rpCompact(fNetTotal) : 'Income - Exp - Saving'}</div>
                                    </div>
                                </div>
                                <div class="kpi-main-row">
                                    <div class="kpi-big-val ${netTotal < 0 ? 'text-danger' : ''}">${rpCompact(netTotal)}</div>
                                    ${kpiBadge(netMonthly[curM], netMonthly[prevM], netTotal, fNetTotal)}
                                </div>
                                <div class="kpi-note">Margin bersih ${netPct}%</div>
                            </div>
                            <div>
                                <div class="kpi-viz">${buildMiniBarsHtml(netMonthly, '#f59e0b', '#fde68a', curM)}</div>
                                <div class="kpi-viz-labels"><span>Jan</span><span>${MONTHS[curM]}</span><span>Des</span></div>
                                <div class="kpi-foot">
                                    <span>${MONTHS[curM]} <strong>${rpCompact(netMonthly[curM])}</strong></span>
                                    <span>Des <strong>${rpCompact(netMonthly[11])}</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="dash-main-grid">
                        <div class="dash-panel">
                            <div class="dash-panel-head">
                                <div>
                                    <div class="fw-bold text-dark">Alokasi Income per Bulan (${isAct ? 'Actual' : 'Forecast'})</div>
                                    <div class="small text-muted">Main Expenses, Additional, Saving &amp; Sisa Kas</div>
                                </div>
                                <span class="badge bg-light text-dark border">12 Bulan</span>
                            </div>
                            <div class="stacked-chart-area">${barsHtml}</div>
                            <div class="chart-legend">
                                <span><i class="legend-dot" style="background:#14b8a6"></i>Main Expenses</span>
                                <span><i class="legend-dot" style="background:#f59e0b"></i>Additional</span>
                                <span><i class="legend-dot" style="background:#8b5cf6"></i>Saving</span>
                                <span><i class="legend-dot" style="background:#38bdf8"></i>Sisa Kas</span>
                            </div>
                        </div>
                        <div class="dash-panel">
                            <div class="dash-panel-head">
                                <div>
                                    <div class="fw-bold text-dark">Cashflow Summary</div>
                                    <div class="small text-muted">Rasio Kesehatan ${currentYear}</div>
                                </div>
                                <span class="badge ${Number(healthPct) >= (state.settings?.savingTargetPct ?? 20) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis'} border">${Number(healthPct) >= (state.settings?.savingTargetPct ?? 20) ? 'Sehat' : 'Perlu Atur'}</span>
                            </div>
                            <div class="p-3 d-flex flex-column justify-content-between flex-grow-1">
                                <div>
                                    <div class="d-flex justify-content-between small text-muted mb-1"><span>Saving + Sisa Kas</span><span>Target ${state.settings?.savingTargetPct ?? 20}%+</span></div>
                                    <div class="fs-3 fw-bold mb-2">${healthPct}%</div>
                                    <div class="progress mb-3" style="height:8px;"><div class="progress-bar bg-success" style="width:${Math.min(100, Number(healthPct))}%"></div></div>
                                    <div class="margin-bar-row"><span style="width:86px;">Main Exp</span><div class="margin-bar-track"><div class="margin-bar-fill" style="width:${Math.min(100, Number(pctMain))}%;background:#14b8a6;"></div></div><span class="fw-semibold">${pctMain}%</span></div>
                                    <div class="margin-bar-row"><span style="width:86px;">Additional</span><div class="margin-bar-track"><div class="margin-bar-fill" style="width:${Math.min(100, Number(pctAdd))}%;background:#f59e0b;"></div></div><span class="fw-semibold">${pctAdd}%</span></div>
                                    <div class="margin-bar-row"><span style="width:86px;">Saving</span><div class="margin-bar-track"><div class="margin-bar-fill" style="width:${Math.min(100, Number(pctSav))}%;background:#8b5cf6;"></div></div><span class="fw-semibold">${pctSav}%</span></div>
                                    <div class="margin-bar-row mb-0"><span style="width:86px;">Sisa Kas</span><div class="margin-bar-track"><div class="margin-bar-fill" style="width:${Math.min(100, Number(pctNet))}%;background:#38bdf8;"></div></div><span class="fw-semibold">${pctNet}%</span></div>
                                </div>
                                <div class="mt-3 pt-2 border-top d-flex justify-content-between small">
                                    <span class="text-muted">Total Sisa Kas</span>
                                    <strong class="${netTotal < 0 ? 'text-danger' : 'text-success'}">${rp(netTotal)}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="summarySection" class="mb-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-dark"><i class="bi bi-table"></i> FINANCIAL SUMMARY STATEMENT (${isAct ? 'ACTUAL vs FORECAST' : 'FORECAST'})</span>
                                <span class="badge bg-light text-secondary border">FY${currentYear} · ALL</span>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleExpandAllBtn">
                                    ${Object.values(expandedGroups).every(Boolean) ? '<i class="bi bi-arrows-collapse"></i> Collapse All' : '<i class="bi bi-arrows-expand"></i> Expand All'}
                                </button>
                                <button type="button" class="btn btn-sm btn-success" id="downloadExcelBtn">
                                    <i class="bi bi-file-earmark-excel"></i> Download Excel (.xlsx + Rumus)
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive border rounded">
                            <table class="summary-table">
                                <thead>${sumHead}</thead>
                                <tbody id="summaryBody">${sumBody}</tbody>
                            </table>
                        </div>
                    </div>

                    <div id="inlineSheetSection"></div>
                `;
            }

            function buildDropdownOptions(sectionKey, existingItems) {
                const existingNames = new Set((existingItems || []).map(i => i.name.toLowerCase()));
                const list = (state.presets[sectionKey] || []).filter(p => !existingNames.has(p.toLowerCase()));
                const labelMap = { income: 'Income', main: 'Main', additional: 'Additional', savings: 'Saving' };
                let opts = `<option value="">-- Pilih item ${labelMap[sectionKey] || ''} --</option>`;
                list.forEach(p => { opts += `<option value="${esc(p)}">${esc(p)}</option>`; });
                opts += `<option value="__custom__">+ Ketik item baru...</option>`;
                return opts;
            }

            function renderSectionRows(sectionKey, sectionTitle, subtotalLabel, items, isAct) {
                const field = isAct ? 'actual' : 'forecast';
                const isExp = sectionKey === 'main' || sectionKey === 'additional';
                const filtered = searchQuery ? (items || []).filter(i => i.name.toLowerCase().includes(searchQuery)) : (items || []);
                const dropdownOpts = buildDropdownOptions(sectionKey, items);

                let html = `
                    <tr class="section-row">
                        <td class="col-item"><span class="section-title-label">${esc(sectionTitle)}</span></td>
                        <td colspan="${isAct ? 16 : 14}">
                            <div class="section-add-controls">
                                <select class="form-select form-select-sm section-add-select" data-section="${sectionKey}">${dropdownOpts}</select>
                                <input type="text" class="form-control form-control-sm section-custom-input d-none" data-section="${sectionKey}" placeholder="Nama item baru...">
                                <button type="button" class="btn btn-accent section-add-btn" data-section="${sectionKey}"><i class="bi bi-plus-lg"></i> Add</button>
                                <button type="button" class="btn btn-outline-secondary btn-manage-list manage-list-btn" data-section="${sectionKey}" data-title="${esc(sectionTitle)}"><i class="bi bi-sliders"></i> Kelola List</button>
                            </div>
                        </td>
                    </tr>
                `;

                if (filtered.length === 0) {
                    html += `<tr><td class="col-item text-muted fst-italic">Belum ada item</td><td colspan="${isAct ? 16 : 14}" class="text-start text-muted small">Pilih dari dropdown di atas lalu klik Add</td></tr>`;
                } else {
                    filtered.forEach(item => {
                        const arr = item[field] || [];
                        const fArr = item.forecast || [];
                        let rowTotal = 0, fcstRowTotal = 0;
                        html += `<tr class="item-row"><td class="col-item">${esc(item.name)}</td>`;
                        for (let m = 0; m < 12; m++) {
                            const val = Number(arr[m] || 0);
                            const fVal = Number(fArr[m] || 0);
                            rowTotal += val;
                            fcstRowTotal += fVal;
                            if (isAct) {
                                html += `<td class="editable-cell ${val === 0 ? 'is-zero' : ''}" data-section="${sectionKey}" data-id="${item.id}" data-month="${m}">
                                    <div>${rp(val)}</div>
                                    <span class="shadow-fcst">Fcst: ${rpCompact(fVal)}</span>
                                    ${formatGapHtml(val, fVal, isExp)}
                                </td>`;
                            } else {
                                html += `<td class="editable-cell ${val === 0 ? 'is-zero' : ''}" data-section="${sectionKey}" data-id="${item.id}" data-month="${m}">${rp(val)}</td>`;
                            }
                        }
                        html += `<td class="col-year-total">${rp(rowTotal)}</td>`;
                        if (isAct) {
                            html += `<td class="col-year-total text-muted">${rp(fcstRowTotal)}</td>`;
                            html += `<td class="col-year-total">${formatGapHtml(rowTotal, fcstRowTotal, isExp)}</td>`;
                        }
                        html += `<td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger p-0 row-actions delete-btn" data-section="${sectionKey}" data-id="${item.id}"><i class="bi bi-trash"></i></button></td></tr>`;
                    });
                }

                const sums = sumSection(filtered, field);
                const fSums = sumSection(filtered, 'forecast');
                html += `<tr class="subtotal-row"><td class="col-item">${esc(subtotalLabel)}</td>`;
                for (let m = 0; m < 12; m++) {
                    if (isAct) {
                        html += `<td><div>${rp(sums.monthly[m])}</div><span class="shadow-fcst">Fcst: ${rpCompact(fSums.monthly[m])}</span>${formatGapHtml(sums.monthly[m], fSums.monthly[m], isExp)}</td>`;
                    } else {
                        html += `<td>${rp(sums.monthly[m])}</td>`;
                    }
                }
                html += `<td class="col-year-total">${rp(sums.total)}</td>`;
                if (isAct) {
                    html += `<td class="col-year-total">${rp(fSums.total)}</td><td class="col-year-total">${formatGapHtml(sums.total, fSums.total, isExp)}</td>`;
                }
                html += `<td></td></tr>`;
                return { html, sums, fSums };
            }

            function renderSheetView(yearData, mode) {
                const isAct = mode === 'actual';
                const yrShort = currentYear.slice(-2);
                let bodyHtml = '';
                if (currentSheetTab === 'income') {
                    bodyHtml = renderSectionRows('income', 'INCOME', 'Total Income', yearData.income, isAct).html;
                } else if (currentSheetTab === 'expenses') {
                    const s1 = renderSectionRows('main', 'MAIN EXPENSES', 'Total Main Expenses', yearData.main, isAct);
                    const s2 = renderSectionRows('additional', 'ADDITIONAL EXPENSES', 'Total Additional Expenses', yearData.additional, isAct);
                    const grand = s1.sums.total + s2.sums.total;
                    const fGrand = s1.fSums.total + s2.fSums.total;
                    bodyHtml = s1.html + s2.html + `<tr class="grandtotal-row"><td class="col-item">Total Expenses</td>`;
                    for (let m = 0; m < 12; m++) {
                        const mTot = s1.sums.monthly[m] + s2.sums.monthly[m];
                        const fmTot = s1.fSums.monthly[m] + s2.fSums.monthly[m];
                        bodyHtml += isAct
                            ? `<td><div>${rp(mTot)}</div><span class="shadow-fcst">Fcst: ${rpCompact(fmTot)}</span>${formatGapHtml(mTot, fmTot, true)}</td>`
                            : `<td>${rp(mTot)}</td>`;
                    }
                    bodyHtml += `<td class="col-year-total">${rp(grand)}</td>`;
                    if (isAct) bodyHtml += `<td class="col-year-total">${rp(fGrand)}</td><td class="col-year-total">${formatGapHtml(grand, fGrand, true)}</td>`;
                    bodyHtml += `<td></td></tr>`;
                } else {
                    bodyHtml = renderSectionRows('savings', 'SAVING', 'Total Saving', yearData.savings, isAct).html;
                }

                document.getElementById('inlineSheetSection').innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2 pt-2 border-top">
                        <div>
                            <h5 class="mb-0 fw-bold">${isAct ? 'Annual Sheet — Actual (Shadow Forecast & Gap)' : 'Annual Sheet — Forecast'} (${currentYear})</h5>
                            <div class="small text-muted">${isAct ? 'Setiap sel menampilkan angka Actual beserta shadow Forecast &amp; Gap (Actual - Forecast). Klik sel untuk isi Actual.' : 'Isi rencana/forecast 1 tahun penuh. Data ini otomatis menjadi shadow di halaman Actual Calculation.'}</div>
                        </div>
                    </div>
                    <div class="annual-tabs-bar">
                        <div class="annual-tabs-left">
                            <button type="button" class="annual-tab-btn ${currentSheetTab === 'income' ? 'active' : ''}" data-sheet-tab="income">
                                <i class="bi bi-arrow-down-circle"></i> Income
                            </button>
                            <button type="button" class="annual-tab-btn ${currentSheetTab === 'expenses' ? 'active' : ''}" data-sheet-tab="expenses">
                                <i class="bi bi-cart-dash"></i> Expenses
                            </button>
                            <button type="button" class="annual-tab-btn ${currentSheetTab === 'savings' ? 'active' : ''}" data-sheet-tab="savings">
                                <i class="bi bi-piggy-bank"></i> Saving
                            </button>
                        </div>
                        ${isAct ? `<button type="button" class="btn btn-sm btn-outline-primary mb-1" id="copyForecastBtn"><i class="bi bi-copy"></i> Salin Nilai Forecast ke Actual</button>` : ''}
                    </div>
                    <div class="table-responsive border rounded">
                        <table class="sheet-table">
                            <thead><tr>
                                <th class="col-item">Item</th>
                                ${MONTHS.map(m => `<th>${m}-${yrShort}</th>`).join('')}
                                <th>${isAct ? 'Total Act' : 'Total'}</th>
                                ${isAct ? '<th>Total Fcst</th><th>Gap</th>' : ''}
                                <th style="width:40px;"></th>
                            </tr></thead>
                            <tbody id="sheetBody">${bodyHtml}</tbody>
                        </table>
                    </div>
                `;
            }

            function renderLedgerView(yearData) {
                const mIdx = ledgerMonthIdx;
                const mLabel = `${MONTH_NAMES[mIdx]} ${currentYear}`;
                const sumCol = (items, f) => (items || []).reduce((acc, it) => acc + Number((it[f] || [])[mIdx] || 0), 0);
                const totInc = sumCol(yearData.income, 'actual'), totMain = sumCol(yearData.main, 'actual'), totAdd = sumCol(yearData.additional, 'actual'), totSav = sumCol(yearData.savings, 'actual');
                const totExp = totMain + totAdd, totAlloc = totExp + totSav, net = totInc - totAlloc;
                const itemCount = (yearData.main?.length || 0) + (yearData.additional?.length || 0) + (yearData.savings?.length || 0);

                function buildGroupCard(title, items, totVal, isExp = false) {
                    const rows = (items && items.length)
                        ? items.map((it, i) => {
                            const v = Number((it.actual || [])[mIdx] || 0);
                            const fv = Number((it.forecast || [])[mIdx] || 0);
                            const g = v - fv;
                            return `<tr>
                                <td class="text-muted" style="width:42px;">${i + 1}</td>
                                <td>${esc(it.name)}</td>
                                <td class="text-end">${rp(fv)}</td>
                                <td class="text-end fw-semibold">${v > 0 ? rp(v) : '—'}</td>
                                <td class="text-end">${g === 0 ? '—' : (g > 0 ? '+' : '') + rp(g)}</td>
                            </tr>`;
                        }).join('')
                        : `<tr><td colspan="5" class="text-center text-muted py-2">Belum ada item</td></tr>`;
                    return `
                        <div class="card mb-3">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2">
                                <span><i class="bi bi-list-ul me-1"></i> ${esc(title)} <span class="text-white-50 small ms-1">${(items || []).length} item</span></span>
                                <span class="small">Total Actual <strong>${rp(totVal)}</strong></span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th style="width:42px;">No</th><th>Item</th><th class="text-end">Forecast</th><th class="text-end">Actual</th><th class="text-end">Gap</th></tr></thead>
                                    <tbody>${rows}</tbody>
                                </table>
                            </div>
                        </div>
                    `;
                }

                document.getElementById('canvasBody').innerHTML = `
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
                        <div>
                            <h5 class="mb-0 fw-bold">Ledger ${mLabel}</h5>
                            <div class="small text-muted">Pilih bulan tertentu untuk preview &amp; download 1 file (PDF / Excel)</div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <select class="form-select form-select-sm" id="ledgerMonthSelect" style="width:150px;">
                                ${MONTH_NAMES.map((m, i) => `<option value="${i}" ${i === mIdx ? 'selected' : ''}>${m}</option>`).join('')}
                            </select>
                            <button type="button" class="btn btn-sm btn-dark" onclick="window.print()"><i class="bi bi-file-earmark-pdf"></i> Download PDF / Cetak</button>
                            <button type="button" class="btn btn-sm btn-success" id="downloadLedgerExcelBtn"><i class="bi bi-file-earmark-excel"></i> Download Excel (.xlsx)</button>
                        </div>
                    </div>
                    <div id="ledgerCapture">
                        <div class="border-bottom pb-2 mb-3">
                            <div class="small text-uppercase text-muted fw-semibold"><i class="bi bi-piggy-bank"></i> SEMPOA</div>
                            <h4 class="mb-0 fw-bold">Ledger ${mLabel}</h4>
                        </div>
                        <div class="card mb-3">
                            <div class="card-header bg-dark text-white py-2"><i class="bi bi-clipboard-data me-1"></i> Ringkasan Actual</div>
                            <table class="table table-sm mb-0"><tbody>
                                <tr><th>Total Income</th><td class="text-end">${rp(totInc)}</td><th>Total Expenses</th><td class="text-end text-danger">${rp(totExp)}</td><th>Total Saving</th><td class="text-end text-success">${rp(totSav)}</td></tr>
                                <tr><th>Total Dialokasikan</th><td class="text-end">${rp(totAlloc)}</td><th>Sisa Kas</th><td class="text-end fw-bold">${rp(net)}</td><th>Jumlah Item</th><td class="text-end">${itemCount} item</td></tr>
                            </tbody></table>
                        </div>
                        ${buildGroupCard('Income', yearData.income, totInc)}
                        ${buildGroupCard('Main Expenses', yearData.main, totMain, true)}
                        ${buildGroupCard('Additional Expenses', yearData.additional, totAdd, true)}
                        ${buildGroupCard('Saving', yearData.savings, totSav)}
                    </div>
                `;
            }

            function renderSettingsView() {
                const s = state.settings || DEFAULT_SETTINGS;
                const yearsList = Object.keys(state.years || {}).sort();
                const secLabels = { income: 'Income', main: 'Main Expenses', additional: 'Additional Expenses', savings: 'Saving' };

                document.getElementById('canvasBody').innerHTML = `
                    <div class="mb-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-gear me-1"></i> Settings — Pengaturan Sempoa</h5>
                        <div class="small text-muted">Atur profil aplikasi, target keuangan, tahun aktif, master dropdown, serta backup/restore data.</div>
                    </div>

                    <div class="row g-3">
                        <!-- 1. Profil & Identitas -->
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header bg-dark text-white py-2 fw-semibold small"><i class="bi bi-person-badge me-1"></i> Identitas &amp; Tampilan</div>
                                <div class="card-body">
                                    <form id="settingsGeneralForm" class="row g-2">
                                        <div class="col-12">
                                            <label class="form-label small mb-1">Nama Aplikasi</label>
                                            <input type="text" class="form-control form-control-sm" id="setAppName" value="${esc(s.appName)}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small mb-1">Nama Pemilik</label>
                                            <input type="text" class="form-control form-control-sm" id="setUserName" value="${esc(s.userName)}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small mb-1">Username / Handle</label>
                                            <input type="text" class="form-control form-control-sm" id="setUserHandle" value="${esc(s.userHandle)}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small mb-1">Target Saving Rate (%)</label>
                                            <input type="number" class="form-control form-control-sm" id="setSavingTarget" min="1" max="100" value="${Number(s.savingTargetPct ?? 20)}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small mb-1">Bulan Aktif Dashboard</label>
                                            <select class="form-select form-select-sm" id="setActiveMonth">
                                                ${MONTH_NAMES.map((m, i) => `<option value="${i}" ${i === Number(s.activeMonthIdx ?? 10) ? 'selected' : ''}>${m}</option>`).join('')}
                                            </select>
                                        </div>
                                        <div class="col-12 pt-2">
                                            <button type="submit" class="btn btn-sm btn-dark"><i class="bi bi-check2"></i> Simpan Pengaturan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Tahun & Backup Data -->
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header bg-dark text-white py-2 fw-semibold small"><i class="bi bi-database me-1"></i> Manajemen Tahun &amp; Backup Data</div>
                                <div class="card-body d-flex flex-column justify-content-between gap-3">
                                    <div>
                                        <label class="form-label small mb-1">Tahun Tersedia (${yearsList.join(', ')})</label>
                                        <div class="input-group input-group-sm mb-2">
                                            <input type="number" class="form-control" id="addYearInput" placeholder="Tambah tahun baru, mis. 2028" min="2020" max="2040">
                                            <button type="button" class="btn btn-outline-dark" id="addYearBtn"><i class="bi bi-plus-lg"></i> Tambah Tahun</button>
                                        </div>
                                    </div>
                                    <div class="border-top pt-2">
                                        <div class="small fw-semibold mb-2">Reset Bulanan &amp; Tahunan</div>
                                        <button type="button" class="btn btn-sm btn-danger mb-2" id="settingsOpenResetBtn">
                                            <i class="bi bi-arrow-counterclockwise"></i> Buka Menu Reset Bulan / Tahun
                                        </button>
                                        <div class="small fw-semibold mb-2 mt-2">Backup &amp; Restore Data (JSON)</div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-success" id="exportJsonBtn"><i class="bi bi-download"></i> Export Backup JSON</button>
                                            <label class="btn btn-sm btn-outline-primary mb-0">
                                                <i class="bi bi-upload"></i> Import Backup JSON
                                                <input type="file" id="importJsonInput" accept=".json" hidden>
                                            </label>
                                            <button type="button" class="btn btn-sm btn-outline-danger" id="settingsResetBtn"><i class="bi bi-arrow-counterclockwise"></i> Reset Default</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Master Dropdown Semua Kategori -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-dark text-white py-2 fw-semibold small"><i class="bi bi-sliders me-1"></i> Master Pilihan Dropdown (Income, Main, Additional, Saving)</div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        ${Object.keys(secLabels).map(sec => `
                                            <div class="col-md-6 col-xl-3">
                                                <div class="border rounded p-2 h-100 bg-light">
                                                    <div class="fw-bold small mb-2">${secLabels[sec]}</div>
                                                    <div class="input-group input-group-sm mb-2">
                                                        <input type="text" class="form-control set-preset-inp" data-sec="${sec}" placeholder="Tambah...">
                                                        <button type="button" class="btn btn-dark set-preset-add" data-sec="${sec}"><i class="bi bi-plus"></i></button>
                                                    </div>
                                                    <ul class="list-group list-group-flush border rounded bg-white" style="max-height:170px;overflow-y:auto;">
                                                        ${(state.presets[sec] || []).map((p, idx) => `
                                                            <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 small">
                                                                <span class="text-truncate">${esc(p)}</span>
                                                                <button type="button" class="btn btn-sm btn-link text-danger p-0 set-preset-del" data-sec="${sec}" data-idx="${idx}"><i class="bi bi-x-lg"></i></button>
                                                            </li>
                                                        `).join('')}
                                                    </ul>
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            function syncBrandAndYearSelect() {
                const s = state.settings || DEFAULT_SETTINGS;
                document.querySelector('.brand-name-cute').textContent = s.appName || 'Sempoa';
                document.getElementById('sidebarUserName').textContent = s.userName || 'Ivan Rajendriya';
                document.getElementById('sidebarUserHandle').textContent = s.userHandle || '@ivanrajendriya';
                if (document.getElementById('topUserName')) document.getElementById('topUserName').textContent = s.userName || 'Ivan Rajendriya';
                if (document.getElementById('topUserHandle')) document.getElementById('topUserHandle').textContent = s.userHandle || '@ivanrajendriya';
                const parts = (s.userName || 'IR').trim().split(/\s+/);
                document.getElementById('sidebarAvatar').textContent = parts.map(p => p[0]).join('').slice(0, 2).toUpperCase();

                const ySel = document.getElementById('yearSelect');
                const years = Object.keys(state.years || {}).sort();
                ySel.innerHTML = years.map(y => `<option value="${y}" ${y === currentYear ? 'selected' : ''}>Jan – Des ${y}</option>`).join('');
            }

            function renderRateCardView(yearData) {
                const subMeta = {
                    income:  { title: 'Rate Card — Income',  sections: [{ key: 'income', label: 'Income' }], badge: 'bg-primary' },
                    expense: { title: 'Rate Card — Expense', sections: [{ key: 'main', label: 'Main Expenses' }, { key: 'additional', label: 'Additional Expenses' }], badge: 'bg-danger' },
                    savings: { title: 'Rate Card — Saving',  sections: [{ key: 'savings', label: 'Saving' }], badge: 'bg-success' }
                };
                const meta = subMeta[currentRateCardSub] || subMeta.income;

                let cardsHtml = '';
                meta.sections.forEach(sec => {
                    const items = (yearData[sec.key] || []).filter(it => !searchQuery || it.name.toLowerCase().includes(searchQuery));
                    const rowsHtml = items.length ? items.map((it, idx) => {
                        const fArr = it.forecast || Array(12).fill(0);
                        const aArr = it.actual || Array(12).fill(0);
                        const activeArr = currentRcScenario === 'actual' ? aArr : fArr;
                        const activeMonths = activeArr.filter(v => Number(v) > 0).length;
                        const firstVal = activeArr.find(v => Number(v) > 0) || 0;
                        const totFcst = fArr.reduce((a, b) => a + Number(b || 0), 0);
                        const totAct = aArr.reduce((a, b) => a + Number(b || 0), 0);
                        const freqLabel = it.freq === 'weekly' ? 'Weekly ×4' : (it.freq === 'biweekly' ? 'Biweekly ×2' : (it.freq === 'once' ? 'Once' : 'Monthly'));
                        return `
                            <tr>
                                <td class="text-muted" style="width:40px;">${idx + 1}</td>
                                <td>
                                    <div class="fw-semibold">${esc(it.name)}</div>
                                    <div class="small text-muted">${freqLabel} · ${activeMonths}/12 bln terisi (${currentRcScenario === 'actual' ? 'Actual' : 'Forecast'})</div>
                                </td>
                                <td class="text-end">${rp(firstVal)}</td>
                                <td class="text-end ${currentRcScenario === 'forecast' ? 'fw-bold text-dark' : 'text-muted'}">${rp(totFcst)}</td>
                                <td class="text-end ${currentRcScenario === 'actual' ? 'fw-bold text-primary' : 'text-muted'}">${rp(totAct)}</td>
                                <td class="text-end" style="width:150px;">
                                    <button type="button" class="btn btn-sm btn-outline-dark py-0 px-2 rc-open-edit"
                                            data-sec="${sec.key}" data-id="${it.id}">
                                        <i class="bi bi-sliders2"></i> Edit Rate
                                    </button>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1 delete-btn"
                                            data-section="${sec.key}" data-id="${it.id}" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    }).join('') : `<tr><td colspan="6" class="text-center text-muted py-3">Belum ada item di ${sec.label}. Klik <strong>+ Tambah Rate Card</strong>.</td></tr>`;

                    cardsHtml += `
                        <div class="card mb-3">
                            <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
                                <span class="fw-semibold small">${esc(sec.label)} <span class="badge ${meta.badge} ms-1">${items.length} item</span></span>
                                <button type="button" class="btn btn-sm btn-light py-0 px-2 rc-open-add" data-sec="${sec.key}">
                                    <i class="bi bi-plus-lg"></i> Tambah ${esc(sec.label)}
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>No</th><th>Item &amp; Frekuensi</th><th class="text-end">Rate / Bln</th>
                                            <th class="text-end">Total Forecast</th><th class="text-end">Total Actual</th><th class="text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>${rowsHtml}</tbody>
                                </table>
                            </div>
                        </div>
                    `;
                });

                document.getElementById('canvasBody').innerHTML = `
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                        <div>
                            <h5 class="mb-0 fw-bold">${meta.title} (${currentYear})</h5>
                            <div class="small text-muted">Tambahkan item baru atau atur rate per bulan / multi-tahun sekaligus, otomatis masuk ke Annual Sheet &amp; Dashboard.</div>
                        </div>
                        <div class="d-flex align-items-end gap-3 flex-wrap">
                            <div>
                                <div class="text-muted" style="font-size:0.7rem;font-weight:600;margin-bottom:2px;">Scenario</div>
                                <div class="scenario-pill-wrap">
                                    <button type="button" class="scenario-pill-btn ${currentRcScenario === 'forecast' ? 'active' : ''}" data-rc-scenario="forecast">Forecast</button>
                                    <button type="button" class="scenario-pill-btn ${currentRcScenario === 'actual' ? 'active' : ''}" data-rc-scenario="actual">Actual</button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-accent rc-open-add" data-sec="${meta.sections[0].key}" style="height:32px;">
                                <i class="bi bi-plus-lg"></i> Tambah Rate Card
                            </button>
                        </div>
                    </div>
                    <div class="annual-tabs-bar mb-3">
                        <div class="annual-tabs-left">
                            <button type="button" class="annual-tab-btn ${currentRateCardSub === 'income' ? 'active' : ''}" data-sheet-tab="income" data-rc-tab="income">
                                <i class="bi bi-arrow-down-circle"></i> Income
                            </button>
                            <button type="button" class="annual-tab-btn ${currentRateCardSub === 'expense' ? 'active' : ''}" data-sheet-tab="expenses" data-rc-tab="expense">
                                <i class="bi bi-cart-dash"></i> Expense
                            </button>
                            <button type="button" class="annual-tab-btn ${currentRateCardSub === 'savings' ? 'active' : ''}" data-sheet-tab="savings" data-rc-tab="savings">
                                <i class="bi bi-piggy-bank"></i> Saving
                            </button>
                        </div>
                    </div>
                    ${cardsHtml}
                `;
            }

            function calcRcEffectiveAmount() {
                const base = Math.max(0, Number(document.getElementById('rcAmount').value || 0));
                const qty = Math.max(1, Number(document.getElementById('rcQty').value || 1));
                const freq = document.getElementById('rcFreq').value;
                const mult = freq === 'weekly' ? 4 : (freq === 'biweekly' ? 2 : 1);
                const feePct = Number(document.getElementById('rcFeePct').value || 0);
                const raw = base * qty * mult;
                return Math.round(raw * (1 + (feePct / 100)));
            }

            function renderRcGridAndPreview() {
                const years = Object.keys(state.years || {}).sort();
                const sec = document.getElementById('rcSection').value;
                const editId = Number(document.getElementById('rcEditId').value || 0);
                const mode = document.getElementById('rcTargetMode').value;
                const field = mode === 'actual' ? 'actual' : 'forecast';

                const itemName = (document.getElementById('rcName').value || '').trim().toLowerCase();
                let existingItem = null;
                const gridHtml = years.map(yr => {
                    const yd = state.years[yr] || {};
                    const item = (yd[sec] || []).find(i => (editId && i.id === editId) || (itemName && i.name.toLowerCase() === itemName));
                    if (item) existingItem = item;
                    const arr = item ? (item[field] || []) : [];
                    const ownCount = arr.filter(v => Number(v) > 0).length;
                    const pills = MONTHS.map((m, idx) => {
                        const key = `${yr}-${idx}`;
                        const isSel = rcEditMode === 'base' || rcSelectedMonths.has(key);
                        const hasOwn = Number(arr[idx] || 0) > 0;
                        return `<div class="rc-m-pill ${isSel ? 'selected' : ''} ${hasOwn ? 'has-own' : ''}" data-rc-key="${key}">${m}</div>`;
                    }).join('');
                    return `
                        <div class="rc-month-row">
                            <div class="rc-year-lbl" data-rc-year="${yr}" title="Klik untuk pilih / batal pilih semua bulan di ${yr}">${yr}<div class="rc-year-sub">${ownCount} own</div></div>
                            <div class="rc-months-grid">${pills}</div>
                        </div>
                    `;
                }).join('');
                document.getElementById('rcApplyGrid').innerHTML = gridHtml;

                const eff = calcRcEffectiveAmount();
                document.getElementById('rcTotalPreview').textContent = rp(eff);
                const count = rcEditMode === 'base' ? (years.length * 12) : rcSelectedMonths.size;
                document.getElementById('rcSelectedCount').textContent = rcEditMode === 'base' ? 'Semua bulan & tahun' : `${count} selected`;

                if (rcEditMode === 'base') {
                    document.getElementById('rcScopeBadge').innerHTML = `<i class="bi bi-layers"></i> Base rate (semua bulan)`;
                    document.getElementById('rcChangesLabel').textContent = `Semua bulan (${years.join(', ')})`;
                    document.getElementById('rcUnchangedLabel').textContent = 'Tidak ada';
                    document.getElementById('rcSaveBtn').textContent = 'Save all months';
                } else {
                    const keys = Array.from(rcSelectedMonths).sort();
                    const previewTxt = keys.length === 0
                        ? 'Belum ada bulan dipilih'
                        : (keys.length <= 4 ? keys.map(k => { const [y, m] = k.split('-'); return `${MONTHS[m]} ${y}`; }).join(', ') : `${keys.length} bulan dipilih`);
                    document.getElementById('rcScopeBadge').innerHTML = `<i class="bi bi-calendar-event"></i> ${previewTxt}`;
                    document.getElementById('rcChangesLabel').textContent = previewTxt;
                    document.getElementById('rcUnchangedLabel').textContent = 'Bulan lainnya';
                    document.getElementById('rcSaveBtn').textContent = keys.length > 0 ? `Save ${keys.length} month(s)` : 'Save Rate Card';
                }
                document.getElementById('rcChangesAmount').textContent = rp(eff) + ' / bulan';
            }

            function openRateCardDrawer(secKey, itemId = null) {
                const secOpts = [
                    { k: 'income', l: 'Income' },
                    { k: 'main', l: 'Main Expenses' },
                    { k: 'additional', l: 'Additional Expenses' },
                    { k: 'savings', l: 'Saving' }
                ];
                document.getElementById('rcSection').innerHTML = secOpts.map(o => `<option value="${o.k}" ${o.k === secKey ? 'selected' : ''}>${o.l}</option>`).join('');
                document.getElementById('rcTargetMode').value = currentRcScenario;
                document.getElementById('rcEditId').value = itemId ? String(itemId) : '';
                rcEditMode = 'selected';
                document.querySelectorAll('.rc-seg-btn').forEach(b => b.classList.toggle('active', b.dataset.rcEditmode === 'selected'));
                rcSelectedMonths = new Set([`${currentYear}-${Number(state.settings?.activeMonthIdx ?? 10)}`]);

                if (itemId) {
                    const it = (state.years[currentYear]?.[secKey] || []).find(i => i.id === itemId);
                    const arr = currentRcScenario === 'actual' ? it?.actual : it?.forecast;
                    document.getElementById('rcDrawerTitle').textContent = 'Edit rate card';
                    document.getElementById('rcDrawerSub').textContent = `${it?.name || ''} · ${currentYear} · ${currentRcScenario === 'actual' ? 'Actual' : 'Forecast'}`;
                    document.getElementById('rcName').value = it?.name || '';
                    document.getElementById('rcFreq').value = it?.freq || 'monthly';
                    document.getElementById('rcQty').value = it?.qty || 1;
                    document.getElementById('rcFeePct').value = '';
                    const curM = Number(state.settings?.activeMonthIdx ?? 10);
                    document.getElementById('rcAmount').value = Number(arr?.[curM] || arr?.find(v => v > 0) || 0);
                } else {
                    document.getElementById('rcDrawerTitle').textContent = 'Tambah rate card baru';
                    document.getElementById('rcDrawerSub').textContent = `Sempoa · ${currentYear}`;
                    document.getElementById('rcName').value = '';
                    document.getElementById('rcFreq').value = 'monthly';
                    document.getElementById('rcQty').value = 1;
                    document.getElementById('rcFeePct').value = '';
                    document.getElementById('rcAmount').value = 0;
                }
                renderRcGridAndPreview();
                rateCardDrawer.show();
            }

            function render() {
                syncBrandAndYearSelect();
                const yearData = state.years[currentYear] || { income: [], main: [], additional: [], savings: [] };
                renderSidebarStats(yearData);

                document.querySelectorAll('.sub-nav-btn[data-rc-sub]').forEach(b => {
                    b.classList.toggle('active', currentNav === 'ratecard' && b.dataset.rcSub === currentRateCardSub);
                });

                const canvas = document.getElementById('appCanvas');
                canvas.classList.remove('theme-teams', 'theme-word', 'theme-ppt', 'theme-excel');
                const modeBadge = document.getElementById('topModeBadge');
                const bread = document.getElementById('topBreadcrumbCurrent');

                if (currentNav === 'forecast' || currentNav === 'actual') {
                    const mode = currentNav;
                    canvas.classList.add(currentSheetTab === 'income' ? 'theme-word' : (currentSheetTab === 'expenses' ? 'theme-ppt' : 'theme-excel'));
                    modeBadge.textContent = mode === 'actual' ? 'Actual Calculation' : 'Forecast Calculation';
                    bread.textContent = 'Dashboard & Annual Sheet';
                    renderDashboardView(yearData, mode);
                    renderSheetView(yearData, mode);
                } else if (currentNav === 'ratecard') {
                    canvas.classList.add(currentRateCardSub === 'income' ? 'theme-word' : (currentRateCardSub === 'expense' ? 'theme-ppt' : 'theme-excel'));
                    modeBadge.textContent = 'Rate Card';
                    bread.textContent = currentRateCardSub === 'income' ? 'Income' : (currentRateCardSub === 'expense' ? 'Expense' : 'Saving');
                    renderRateCardView(yearData);
                } else if (currentNav === 'ledger') {
                    canvas.classList.add('theme-excel');
                    modeBadge.textContent = 'Laporan';
                    bread.textContent = 'Ledger (Per Bulan)';
                    renderLedgerView(yearData);
                } else {
                    canvas.classList.add('theme-teams');
                    modeBadge.textContent = 'Pengaturan';
                    bread.textContent = 'Settings';
                    renderSettingsView();
                }
            }

            async function exportSummaryExcel() {
                if (typeof ExcelJS === 'undefined') return alert('Library ExcelJS belum termuat.');
                const isAct = isActualMode();
                const field = isAct ? 'actual' : 'forecast';
                const yearData = state.years[currentYear] || { income: [], main: [], additional: [], savings: [] };
                const wb = new ExcelJS.Workbook();
                const ws = wb.addWorksheet(`${isAct ? 'Actual' : 'Forecast'} ${currentYear}`, { views: [{ state: 'frozen', xSplit: 1, ySplit: 2 }] });
                const cols = ['B','C','D','E','F','G','H','I','J','K','L','M'];
                const CURR_FMT = '"Rp "#,##0;-"Rp "#,##0;"Rp "0';

                ws.mergeCells('A1:N1');
                const t = ws.getCell('A1');
                t.value = `SEMPOA — ${isAct ? 'ACTUAL' : 'FORECAST'} SUMMARY ${currentYear}`;
                t.font = { bold: true, size: 13, color: { argb: 'FFFFFFFF' } };
                t.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF217346' } };
                ws.getRow(1).height = 24;

                const hdr = ws.addRow(['DETAILS', ...MONTHS.map(m => `${m}-${currentYear.slice(-2)}`), 'TOTAL']);
                hdr.font = { bold: true, color: { argb: 'FFFFFFFF' } };
                hdr.eachCell(c => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0F172A' } }; });

                function addSec(title, items, subLbl, fillHex) {
                    ws.addRow([title]).font = { bold: true };
                    const start = ws.rowCount + 1;
                    (items || []).forEach(it => {
                        const arr = it[field] || Array(12).fill(0);
                        const r = ws.addRow([it.name, ...arr.map(v => Number(v || 0)), null]);
                        r.getCell(14).value = { formula: `SUM(B${r.number}:M${r.number})` };
                        for (let c = 2; c <= 14; c++) r.getCell(c).numFmt = CURR_FMT;
                    });
                    const end = ws.rowCount;
                    const sub = ws.addRow([subLbl]); sub.font = { bold: true };
                    for (let c = 2; c <= 13; c++) {
                        sub.getCell(c).value = end >= start ? { formula: `SUM(${cols[c - 2]}${start}:${cols[c - 2]}${end})` } : 0;
                        sub.getCell(c).numFmt = CURR_FMT;
                    }
                    sub.getCell(14).value = { formula: `SUM(B${sub.number}:M${sub.number})` };
                    sub.getCell(14).numFmt = CURR_FMT;
                    for (let c = 1; c <= 14; c++) sub.getCell(c).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: fillHex } };
                    return sub.number;
                }

                const incR = addSec('INCOME', yearData.income, 'Total Income', 'FFEAF1FB');
                const mainR = addSec('MAIN EXPENSES', yearData.main, 'Total Main Expenses', 'FFFDF0ED');
                const addR = addSec('ADDITIONAL EXPENSES', yearData.additional, 'Total Additional Expenses', 'FFFDF0ED');
                const expR = ws.addRow(['Total Expenses (Main + Additional)']); expR.font = { bold: true };
                for (let c = 2; c <= 13; c++) { expR.getCell(c).value = { formula: `${cols[c - 2]}${mainR}+${cols[c - 2]}${addR}` }; expR.getCell(c).numFmt = CURR_FMT; }
                expR.getCell(14).value = { formula: `SUM(B${expR.number}:M${expR.number})` }; expR.getCell(14).numFmt = CURR_FMT;
                for (let c = 1; c <= 14; c++) expR.getCell(c).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFE4E6' } };

                const savR = addSec('SAVING', yearData.savings, 'Total Saving', 'FFEAF5EE');
                const netR = ws.addRow(['Sisa Kas (Income - Expenses - Saving)']); netR.font = { bold: true };
                for (let c = 2; c <= 13; c++) { netR.getCell(c).value = { formula: `${cols[c - 2]}${incR}-${cols[c - 2]}${expR.number}-${cols[c - 2]}${savR}` }; netR.getCell(c).numFmt = CURR_FMT; }
                netR.getCell(14).value = { formula: `SUM(B${netR.number}:M${netR.number})` }; netR.getCell(14).numFmt = CURR_FMT;
                for (let c = 1; c <= 14; c++) netR.getCell(c).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFDCFCE7' } };

                ws.getColumn(1).width = 36;
                for (let c = 2; c <= 14; c++) ws.getColumn(c).width = 16;
                const buf = await wb.xlsx.writeBuffer();
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
                a.download = `Sempoa_${isAct ? 'Actual' : 'Forecast'}_${currentYear}.xlsx`;
                a.click();
            }

            async function exportLedgerMonthExcel() {
                if (typeof ExcelJS === 'undefined') return alert('Library ExcelJS belum termuat.');
                const mIdx = ledgerMonthIdx, mNum = String(mIdx + 1).padStart(2, '0');
                const yearData = state.years[currentYear] || { income: [], main: [], additional: [], savings: [] };
                const wb = new ExcelJS.Workbook();
                const ws = wb.addWorksheet(`Ledger ${MONTHS[mIdx]} ${currentYear}`);
                const CURR_FMT = '"Rp "#,##0;-"Rp "#,##0;"Rp "0';

                ws.mergeCells('A1:E1');
                ws.getCell('A1').value = `SEMPOA LEDGER — ${MONTH_NAMES[mIdx].toUpperCase()} ${currentYear}`;
                ws.getCell('A1').font = { bold: true, color: { argb: 'FFFFFFFF' } };
                ws.getCell('A1').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0F172A' } };
                ws.addRow(['No', 'Item', 'Forecast', 'Actual', 'Gap']).font = { bold: true };

                function addBlock(title, items, totalLabel, fillHex) {
                    ws.addRow(['', title]).font = { bold: true };
                    const start = ws.rowCount + 1;
                    (items || []).forEach((it, idx) => {
                        const fv = Number((it.forecast || [])[mIdx] || 0);
                        const av = Number((it.actual || [])[mIdx] || 0);
                        const r = ws.addRow([idx + 1, it.name, fv, av, null]);
                        r.getCell(5).value = { formula: `D${r.number}-C${r.number}` };
                        for (let c = 3; c <= 5; c++) r.getCell(c).numFmt = CURR_FMT;
                    });
                    const end = ws.rowCount;
                    const tr = ws.addRow(['', totalLabel,
                        end >= start ? { formula: `SUM(C${start}:C${end})` } : 0,
                        end >= start ? { formula: `SUM(D${start}:D${end})` } : 0,
                        null
                    ]);
                    tr.getCell(5).value = { formula: `D${tr.number}-C${tr.number}` };
                    tr.font = { bold: true };
                    for (let c = 3; c <= 5; c++) tr.getCell(c).numFmt = CURR_FMT;
                    tr.eachCell(c => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: fillHex } }; });
                    return tr.number;
                }

                addBlock('INCOME', yearData.income, 'Total Income', 'FFEAF1FB');
                addBlock('MAIN EXPENSES', yearData.main, 'Total Main Expenses', 'FFFDF0ED');
                addBlock('ADDITIONAL EXPENSES', yearData.additional, 'Total Additional Expenses', 'FFFDF0ED');
                addBlock('SAVING', yearData.savings, 'Total Saving', 'FFEAF5EE');

                ws.getColumn(1).width = 6; ws.getColumn(2).width = 34;
                ws.getColumn(3).width = 18; ws.getColumn(4).width = 18; ws.getColumn(5).width = 18;
                const buf = await wb.xlsx.writeBuffer();
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
                a.download = `Sempoa_Ledger_${currentYear}-${mNum}.xlsx`;
                a.click();
            }

            document.getElementById('canvasBody').addEventListener('submit', function (e) {
                if (e.target.id === 'settingsGeneralForm') {
                    e.preventDefault();
                    state.settings = {
                        appName: document.getElementById('setAppName').value.trim() || 'Sempoa',
                        userName: document.getElementById('setUserName').value.trim() || 'Ivan Rajendriya',
                        userHandle: document.getElementById('setUserHandle').value.trim() || '@ivanrajendriya',
                        savingTargetPct: Math.max(1, Math.min(100, Number(document.getElementById('setSavingTarget').value || 20))),
                        activeMonthIdx: Number(document.getElementById('setActiveMonth').value || 10)
                    };
                    saveState();
                    render();
                    alert('Pengaturan berhasil disimpan!');
                }
            });

            document.getElementById('canvasBody').addEventListener('click', function (e) {
                if (e.target.closest('#addYearBtn')) {
                    const y = document.getElementById('addYearInput').value.trim();
                    if (!/^\d{4}$/.test(y)) return alert('Masukkan 4 digit tahun, mis. 2028');
                    if (!state.years[y]) {
                        state.years[y] = { income: [], main: [], additional: [], savings: [] };
                        saveState();
                    }
                    currentYear = y;
                    return render();
                }
                if (e.target.closest('#exportJsonBtn')) {
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(new Blob([JSON.stringify(state, null, 2)], { type: 'application/json' }));
                    a.download = `Sempoa_Backup_${new Date().toISOString().slice(0,10)}.json`;
                    return a.click();
                }
                if (e.target.closest('#settingsOpenResetBtn')) {
                    return openResetModal();
                }
                if (e.target.closest('#settingsResetBtn')) {
                    state = { years: structuredClone(DEFAULT_YEARS), presets: structuredClone(DEFAULT_PRESETS), settings: structuredClone(DEFAULT_SETTINGS) };
                    saveState();
                    return render();
                }
                const setPresetAdd = e.target.closest('.set-preset-add');
                if (setPresetAdd) {
                    const sec = setPresetAdd.dataset.sec;
                    const inp = document.querySelector(`.set-preset-inp[data-sec="${sec}"]`);
                    const val = inp.value.trim();
                    if (!val) return;
                    if (!state.presets[sec].some(p => p.toLowerCase() === val.toLowerCase())) {
                        state.presets[sec].push(val); saveState();
                    }
                    return render();
                }
                const setPresetDel = e.target.closest('.set-preset-del');
                if (setPresetDel) {
                    const sec = setPresetDel.dataset.sec, idx = Number(setPresetDel.dataset.idx);
                    state.presets[sec].splice(idx, 1); saveState(); return render();
                }
                const rcScenBtn = e.target.closest('[data-rc-scenario]');
                if (rcScenBtn) {
                    currentRcScenario = rcScenBtn.dataset.rcScenario;
                    return render();
                }
                const rcTabBtn = e.target.closest('[data-rc-tab]');
                if (rcTabBtn) {
                    currentRateCardSub = rcTabBtn.dataset.rcTab;
                    return render();
                }
                const rcOpenAdd = e.target.closest('.rc-open-add');
                if (rcOpenAdd) return openRateCardDrawer(rcOpenAdd.dataset.sec, null);
                const rcOpenEdit = e.target.closest('.rc-open-edit');
                if (rcOpenEdit) return openRateCardDrawer(rcOpenEdit.dataset.sec, Number(rcOpenEdit.dataset.id));

                const sheetTabBtn = e.target.closest('.annual-tab-btn[data-sheet-tab]');
                if (sheetTabBtn) { currentSheetTab = sheetTabBtn.dataset.sheetTab; return render(); }
                if (e.target.closest('#copyForecastBtn')) {
                    const yd = state.years[currentYear];
                    ['income','main','additional','savings'].forEach(sec => {
                        (yd[sec] || []).forEach(it => { it.actual = [...(it.forecast || Array(12).fill(0))]; });
                    });
                    saveState(); return render();
                }
                if (e.target.closest('#downloadExcelBtn')) return exportSummaryExcel();
                if (e.target.closest('#downloadLedgerExcelBtn')) return exportLedgerMonthExcel();
                if (e.target.closest('#toggleExpandAllBtn')) {
                    const allExp = Object.values(expandedGroups).every(Boolean);
                    Object.keys(expandedGroups).forEach(k => expandedGroups[k] = !allExp);
                    return render();
                }
                const grpTr = e.target.closest('tr[data-group]');
                if (grpTr) { expandedGroups[grpTr.dataset.group] = !expandedGroups[grpTr.dataset.group]; return render(); }
                const manageBtn = e.target.closest('.manage-list-btn');
                if (manageBtn) {
                    managingSection = manageBtn.dataset.section;
                    document.getElementById('manageListTitle').innerHTML = `<i class="bi bi-sliders"></i> Kelola Dropdown — ${esc(manageBtn.dataset.title)}`;
                    renderManageList(); return manageModal.show();
                }
                const addBtn = e.target.closest('.section-add-btn');
                if (addBtn) {
                    const sec = addBtn.dataset.section;
                    const select = document.querySelector(`.section-add-select[data-section="${sec}"]`);
                    const customInput = document.querySelector(`.section-custom-input[data-section="${sec}"]`);
                    let name = select.value === '__custom__' ? customInput.value.trim() : select.value.trim();
                    if (!name) return;
                    if (select.value === '__custom__' && !state.presets[sec].some(p => p.toLowerCase() === name.toLowerCase())) {
                        state.presets[sec].push(name);
                    }
                    state.years[currentYear][sec].push({ id: Date.now(), name, forecast: Array(12).fill(0), actual: Array(12).fill(0) });
                    saveState(); return render();
                }
                const delBtn = e.target.closest('.delete-btn');
                if (delBtn) {
                    const sec = delBtn.dataset.section, id = Number(delBtn.dataset.id);
                    state.years[currentYear][sec] = state.years[currentYear][sec].filter(i => i.id !== id);
                    saveState(); return render();
                }
                const td = e.target.closest('.editable-cell');
                if (td && !td.querySelector('input')) {
                    const sec = td.dataset.section, id = Number(td.dataset.id), m = Number(td.dataset.month);
                    const field = isActualMode() ? 'actual' : 'forecast';
                    const item = state.years[currentYear][sec].find(i => i.id === id);
                    if (!item) return;
                    if (!item[field]) item[field] = Array(12).fill(0);
                    const input = document.createElement('input');
                    input.type = 'number'; input.className = 'cell-inline-input';
                    input.value = Number(item[field][m] || 0); input.min = 0; input.step = 1000;
                    td.textContent = ''; td.appendChild(input); input.focus(); input.select();
                    const commit = () => { item[field][m] = Math.max(0, Number(input.value || 0)); saveState(); render(); };
                    input.addEventListener('blur', commit);
                    input.addEventListener('keydown', ev => { if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); } else if (ev.key === 'Escape') render(); });
                }
            });

            document.getElementById('canvasBody').addEventListener('change', function (e) {
                if (e.target.id === 'importJsonInput' && e.target.files[0]) {
                    const reader = new FileReader();
                    reader.onload = ev => {
                        try {
                            const parsed = JSON.parse(ev.target.result);
                            if (parsed.years && parsed.presets) {
                                state = parsed;
                                if (!state.settings) state.settings = structuredClone(DEFAULT_SETTINGS);
                                saveState();
                                render();
                                alert('Backup berhasil di-import!');
                            }
                        } catch (err) { alert('File JSON tidak valid.'); }
                    };
                    reader.readAsText(e.target.files[0]);
                    return;
                }
                if (e.target.id === 'ledgerMonthSelect') { ledgerMonthIdx = Number(e.target.value); return render(); }
                const sel = e.target.closest('.section-add-select');
                if (sel) {
                    const inp = document.querySelector(`.section-custom-input[data-section="${sel.dataset.section}"]`);
                    if (inp) { inp.classList.toggle('d-none', sel.value !== '__custom__'); if (sel.value === '__custom__') inp.focus(); }
                }
            });

            function renderManageList() {
                const listEl = document.getElementById('managePresetList');
                const items = state.presets[managingSection] || [];
                listEl.innerHTML = items.length
                    ? items.map((n, i) => `<li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 small"><span>${esc(n)}</span><button type="button" class="btn btn-sm btn-link text-danger p-0 remove-preset-btn" data-idx="${i}"><i class="bi bi-trash"></i></button></li>`).join('')
                    : '<li class="list-group-item text-muted small text-center py-3">Kosong</li>';
            }

            document.getElementById('addPresetForm').addEventListener('submit', function (e) {
                e.preventDefault();
                const val = document.getElementById('newPresetInput').value.trim();
                if (!val) return;
                if (!state.presets[managingSection].some(p => p.toLowerCase() === val.toLowerCase())) {
                    state.presets[managingSection].push(val); saveState();
                }
                document.getElementById('newPresetInput').value = '';
                renderManageList(); render();
            });
            document.getElementById('managePresetList').addEventListener('click', function (e) {
                const btn = e.target.closest('.remove-preset-btn');
                if (!btn) return;
                state.presets[managingSection].splice(Number(btn.dataset.idx), 1);
                saveState(); renderManageList(); render();
            });

            function closeMobileSidebar() {
                document.getElementById('appSidebar')?.classList.remove('open');
                document.getElementById('sidebarBackdrop')?.classList.remove('show');
            }
            document.getElementById('mobileMenuBtn')?.addEventListener('click', function () {
                document.getElementById('appSidebar')?.classList.toggle('open');
                document.getElementById('sidebarBackdrop')?.classList.toggle('show');
            });
            document.getElementById('sidebarBackdrop')?.addEventListener('click', closeMobileSidebar);

            document.querySelectorAll('.side-nav-btn[data-nav]').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.side-nav-btn[data-nav]').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentNav = this.dataset.nav;
                    closeMobileSidebar();
                    render();
                });
            });

            document.querySelectorAll('.sub-nav-btn[data-rc-sub]').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.side-nav-btn[data-nav]').forEach(b => b.classList.toggle('active', b.dataset.nav === 'ratecard'));
                    currentNav = 'ratecard';
                    currentRateCardSub = this.dataset.rcSub;
                    closeMobileSidebar();
                    render();
                });
            });

            // Rate Card Drawer interactions (segmented toggle, drag-select months, live calc, submit)
            document.querySelectorAll('.rc-seg-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.rc-seg-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    rcEditMode = this.dataset.rcEditmode;
                    renderRcGridAndPreview();
                });
            });
            document.getElementById('rcTargetMode')?.addEventListener('change', function () {
                const editId = Number(document.getElementById('rcEditId').value || 0);
                if (editId) {
                    const sec = document.getElementById('rcSection').value;
                    const it = (state.years[currentYear]?.[sec] || []).find(i => i.id === editId);
                    if (it) {
                        const arr = this.value === 'actual' ? it.actual : it.forecast;
                        const curM = Number(state.settings?.activeMonthIdx ?? 10);
                        document.getElementById('rcAmount').value = Number(arr?.[curM] || arr?.find(v => v > 0) || 0);
                    }
                }
                renderRcGridAndPreview();
            });
            ['rcAmount', 'rcQty', 'rcFreq', 'rcFeePct', 'rcSection'].forEach(id => {
                document.getElementById(id)?.addEventListener('input', renderRcGridAndPreview);
                document.getElementById(id)?.addEventListener('change', renderRcGridAndPreview);
            });
            document.getElementById('rcApplyGrid').addEventListener('click', function (e) {
                const yrLbl = e.target.closest('.rc-year-lbl[data-rc-year]');
                if (!yrLbl || rcEditMode === 'base') return;
                const yr = yrLbl.dataset.rcYear;
                const allSelected = MONTHS.every((_, idx) => rcSelectedMonths.has(`${yr}-${idx}`));
                MONTHS.forEach((_, idx) => {
                    const key = `${yr}-${idx}`;
                    if (allSelected) rcSelectedMonths.delete(key);
                    else rcSelectedMonths.add(key);
                });
                renderRcGridAndPreview();
            });
            document.getElementById('rcApplyGrid').addEventListener('mousedown', function (e) {
                const pill = e.target.closest('.rc-m-pill');
                if (!pill || rcEditMode === 'base') return;
                rcIsDragging = true;
                const k = pill.dataset.rcKey;
                rcDragAdd = !rcSelectedMonths.has(k);
                if (rcDragAdd) rcSelectedMonths.add(k); else rcSelectedMonths.delete(k);
                renderRcGridAndPreview();
            });
            document.getElementById('rcApplyGrid').addEventListener('mouseover', function (e) {
                if (!rcIsDragging) return;
                const pill = e.target.closest('.rc-m-pill');
                if (!pill) return;
                const k = pill.dataset.rcKey;
                if (rcDragAdd) rcSelectedMonths.add(k); else rcSelectedMonths.delete(k);
                renderRcGridAndPreview();
            });
            window.addEventListener('mouseup', () => { rcIsDragging = false; });

            document.getElementById('rateCardForm').addEventListener('submit', function (e) {
                e.preventDefault();
                const sec = document.getElementById('rcSection').value;
                const name = document.getElementById('rcName').value.trim();
                if (!name) return;
                const editId = Number(document.getElementById('rcEditId').value || 0);
                const mode = document.getElementById('rcTargetMode').value;
                const freq = document.getElementById('rcFreq').value;
                const qty = Math.max(1, Number(document.getElementById('rcQty').value || 1));
                const eff = calcRcEffectiveAmount();
                const years = Object.keys(state.years || {}).sort();

                const targetKeys = rcEditMode === 'base'
                    ? years.flatMap(y => MONTHS.map((_, m) => `${y}-${m}`))
                    : Array.from(rcSelectedMonths);

                if (targetKeys.length === 0) return alert('Pilih minimal 1 bulan di bagian "Apply to".');

                if (!state.presets[sec].some(p => p.toLowerCase() === name.toLowerCase())) {
                    state.presets[sec].push(name);
                }

                const newId = editId || Date.now();
                targetKeys.forEach(k => {
                    const [yr, mStr] = k.split('-');
                    const mIdx = Number(mStr);
                    if (!state.years[yr]) state.years[yr] = { income: [], main: [], additional: [], savings: [] };
                    let item = (state.years[yr][sec] || []).find(i => i.id === newId || i.name.toLowerCase() === name.toLowerCase());
                    if (!item) {
                        item = { id: newId, name, freq, qty, forecast: Array(12).fill(0), actual: Array(12).fill(0) };
                        state.years[yr][sec].push(item);
                    } else {
                        item.name = name; item.freq = freq; item.qty = qty;
                    }
                    if (mode === 'forecast' || mode === 'both') {
                        if (!item.forecast) item.forecast = Array(12).fill(0);
                        item.forecast[mIdx] = eff;
                    }
                    if (mode === 'actual' || mode === 'both') {
                        if (!item.actual) item.actual = Array(12).fill(0);
                        item.actual[mIdx] = eff;
                    }
                });

                saveState();
                rateCardDrawer.hide();
                render();
            });
            document.getElementById('yearSelect').addEventListener('change', function () { currentYear = this.value; render(); });
            function openResetModal() {
                const years = Object.keys(state.years || {}).sort();
                const yOpts = years.map(y => `<option value="${y}" ${y === currentYear ? 'selected' : ''}>${y}</option>`).join('');
                document.getElementById('modalResetMonthYear').innerHTML = yOpts;
                document.getElementById('modalResetYearSelect').innerHTML = yOpts;
                document.getElementById('modalResetMonthIdx').innerHTML = MONTH_NAMES.map((m, i) => `<option value="${i}" ${i === Number(state.settings?.activeMonthIdx ?? 10) ? 'selected' : ''}>${m}</option>`).join('');
                resetModal.show();
            }

            document.getElementById('topResetBtn').addEventListener('click', openResetModal);

            document.getElementById('btnExecResetMonth').addEventListener('click', function () {
                const yr = document.getElementById('modalResetMonthYear').value;
                const mIdx = Number(document.getElementById('modalResetMonthIdx').value);
                const target = document.getElementById('modalResetMonthTarget').value;
                const label = `${MONTH_NAMES[mIdx]} ${yr}`;
                if (!confirm(`Yakin ingin mereset data ${target === 'both' ? 'Forecast & Actual' : target} untuk bulan ${label}?`)) return;

                const yd = state.years[yr];
                if (yd) {
                    ['income', 'main', 'additional', 'savings'].forEach(sec => {
                        (yd[sec] || []).forEach(it => {
                            if (target === 'forecast' || target === 'both') {
                                if (!it.forecast) it.forecast = Array(12).fill(0);
                                it.forecast[mIdx] = 0;
                            }
                            if (target === 'actual' || target === 'both') {
                                if (!it.actual) it.actual = Array(12).fill(0);
                                it.actual[mIdx] = 0;
                            }
                        });
                    });
                    saveState();
                }
                resetModal.hide();
                render();
            });

            document.getElementById('btnExecResetYear').addEventListener('click', function () {
                const yr = document.getElementById('modalResetYearSelect').value;
                const mode = document.getElementById('modalResetYearMode').value;
                const msg = mode === 'clear_items'
                    ? `Yakin ingin MENGHAPUS SEMUA ITEM di tahun ${yr}?`
                    : `Yakin ingin menolkan semua data ${mode === 'both' ? 'Forecast & Actual' : mode} sepanjang tahun ${yr}?`;
                if (!confirm(msg)) return;

                if (mode === 'clear_items') {
                    state.years[yr] = { income: [], main: [], additional: [], savings: [] };
                } else {
                    const yd = state.years[yr];
                    if (yd) {
                        ['income', 'main', 'additional', 'savings'].forEach(sec => {
                            (yd[sec] || []).forEach(it => {
                                if (mode === 'forecast' || mode === 'both') it.forecast = Array(12).fill(0);
                                if (mode === 'actual' || mode === 'both') it.actual = Array(12).fill(0);
                            });
                        });
                    }
                }
                saveState();
                resetModal.hide();
                render();
            });

            document.getElementById('topExportBtn').addEventListener('click', () => currentNav === 'ledger' ? exportLedgerMonthExcel() : exportSummaryExcel());
            document.getElementById('globalSearchInput').addEventListener('input', function () { searchQuery = this.value.trim().toLowerCase(); if (currentNav !== 'ledger') render(); });
            document.getElementById('resetDemoBtn').addEventListener('click', () => { state = { years: structuredClone(DEFAULT_YEARS), presets: structuredClone(DEFAULT_PRESETS) }; saveState(); render(); });

            render();
        })();
    </script>
</body>
</html>


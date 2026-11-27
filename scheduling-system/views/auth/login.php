<?php
/**
 * Halaman login — berdiri sendiri (tanpa sidebar/navbar aplikasi)
 * karena pengguna belum punya sesi.
 */
$error    = $error ?? '';
$username = $username ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — Pradiya Portal</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%3E%3Crect%20width='32'%20height='32'%20rx='7'%20fill='%23212529'/%3E%3Cg%20transform='translate(6,6)%20scale(1.25)'%20fill='%23fff'%3E%3Cpath%20d='M10.854,7.146a.5.5,0,0,1,0,.708l-3,3a.5.5,0,0,1-.708,0l-1.5-1.5a.5.5,0,1,1,.708-.708L7.5,9.793l2.646-2.647a.5.5,0,0,1,.708,0z'/%3E%3Cpath%20d='M3.5,0a.5.5,0,0,1,.5.5V1h8V.5a.5.5,0,0,1,1,0V1h1a2,2,0,0,1,2,2v11a2,2,0,0,1-2,2H2a2,2,0,0,1-2-2V3a2,2,0,0,1,2-2h1V.5a.5.5,0,0,1,.5-.5zM1,4v10a1,1,0,0,0,1,1h12a1,1,0,0,0,1-1V4H1z'/%3E%3C/g%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="scheduling-system/public/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../public/assets/css/style.css') ?: '1' ?>">
</head>
<body class="login-page">

<div class="login-split">
    <!-- Panel Kiri: Form Sign In -->
    <div class="login-left">
        <div class="login-form-box">
            <h1 class="login-heading">Sign in</h1>
            <p class="login-subheading">Selamat datang di Pradiya Portal.</p>

            <?php if (!empty($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> py-2 small mb-3" role="alert">
                    <?= htmlspecialchars($_SESSION['flash_message']) ?>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 small mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="index.php?page=auth&action=login" autocomplete="off">
                <?= \Helpers\FormHelper::csrfField() ?>

                <div class="mb-3">
                    <label for="username" class="login-label">Username</label>
                    <input type="text" class="form-control login-input" id="username" name="username"
                           value="<?= htmlspecialchars($username) ?>" placeholder="Masukkan username" required autofocus>
                </div>

                <div class="mb-4">
                    <label for="password" class="login-label">Password</label>
                    <div class="login-password-wrap">
                        <input type="password" class="form-control login-input login-input-password" id="password" name="password"
                               placeholder="••••••••" required>
                        <button type="button" class="login-password-toggle" id="togglePassword"
                                tabindex="-1" aria-label="Tampilkan password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn login-submit-btn w-100">
                    Sign in <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </form>

            <p class="login-footer-note">
                &copy; <?= date('Y') ?> Pradiya Portal &middot; Workforce Automation
            </p>
        </div>
    </div>

    <!-- Panel Kanan: Grid Kotak dengan Logo Website Bergantian + Quote -->
    <div class="login-right" aria-hidden="true">
        <div class="login-logo-grid" id="loginLogoGrid"></div>

        <div class="login-quote-overlay">
            <div class="login-quote-card">
                <div class="login-stars" aria-label="5 stars">
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                </div>
                <blockquote class="login-quote-text">
                    &ldquo;Kemaki nomor siji, skill pikir keri&rdquo;
                </blockquote>
                <div class="login-quote-author">
                    <div class="login-author-avatar">
                        <i class="bi bi-grid-1x2-fill"></i>
                    </div>
                    <div class="login-author-meta">
                        <div class="login-author-name">Pradiya Portal</div>
                        <div class="login-author-role">Ivan &amp; Ricka Workspace</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var toggleBtn = document.getElementById('togglePassword');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            var input = document.getElementById('password');
            var icon  = this.querySelector('i');
            var show  = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    }

    var gridEl = document.getElementById('loginLogoGrid');
    if (!gridEl) return;

    // Daftar logo aplikasi yang ada di website ini
    var logos = [
        {
            name: 'nyatet.',
            html: '<img src="scheduling-system/public/assets/img/nyatet-logo.svg?v=2" alt="nyatet." class="login-grid-logo-img">'
        },
        {
            name: 'Sempoa',
            html: '<img src="scheduling-system/public/assets/img/sempoa-logo.svg" alt="Sempoa" class="login-grid-logo-img">'
        },
        {
            name: 'Rekha Diagram Studio',
            html: '<img src="rekha-favicon.svg" alt="Rekha" class="login-grid-logo-img login-grid-logo-rekha">'
        },
        {
            name: 'Workforce Scheduler',
            html: '<span class="login-grid-logo-icon login-grid-logo-wfm"><i class="bi bi-calendar-check"></i></span>'
        },
        {
            name: 'Pradiya Portal',
            html: '<span class="login-grid-logo-icon login-grid-logo-portal"><i class="bi bi-grid-1x2"></i></span>'
        }
    ];

    var cols = 6;
    var rows = 9;
    var cells = [];
    var eligibleIndices = [];

    function buildGrid() {
        gridEl.innerHTML = '';
        cells = [];
        eligibleIndices = [];

        var total = cols * rows;
        for (var r = 0; r < rows; r++) {
            for (var c = 0; c < cols; c++) {
                var idx = r * cols + c;
                var cell = document.createElement('div');
                cell.className = 'login-grid-cell';
                var slot = document.createElement('div');
                slot.className = 'login-grid-slot';
                cell.appendChild(slot);
                gridEl.appendChild(cell);
                cells.push({ el: cell, slot: slot, row: r, col: c });

                // Sisakan area tengah (baris 3..6, kolom 1..4) agar quote tetap bersih & terbaca
                var inQuoteZone = (r >= 3 && r <= 6 && c >= 1 && c <= 4);
                if (!inQuoteZone) {
                    eligibleIndices.push(idx);
                }
            }
        }
    }

    buildGrid();

    var activeSlots = []; // [{ cellIndex, logoIndex }]
    var nextLogoCursor = 0;
    var maxConcurrent = 3;

    function isAdjacentToActive(candidateIdx) {
        var cand = cells[candidateIdx];
        return activeSlots.some(function (item) {
            var other = cells[item.cellIndex];
            return Math.abs(cand.row - other.row) <= 1 && Math.abs(cand.col - other.col) <= 1;
        });
    }

    function pickFreeCellIndex() {
        var pool = eligibleIndices.filter(function (idx) {
            return !activeSlots.some(function (a) { return a.cellIndex === idx; }) && !isAdjacentToActive(idx);
        });
        if (pool.length === 0) {
            pool = eligibleIndices.filter(function (idx) {
                return !activeSlots.some(function (a) { return a.cellIndex === idx; });
            });
        }
        if (pool.length === 0) return -1;
        return pool[Math.floor(Math.random() * pool.length)];
    }

    function showLogoInCell(cellIdx, logoIdx) {
        var cellObj = cells[cellIdx];
        var logo = logos[logoIdx];
        if (!cellObj || !logo) return;

        cellObj.slot.innerHTML = logo.html;
        cellObj.el.setAttribute('title', logo.name);
        // Force reflow agar transisi CSS berjalan mulus
        void cellObj.el.offsetWidth;
        cellObj.el.classList.add('is-active');
        activeSlots.push({ cellIndex: cellIdx, logoIndex: logoIdx });
    }

    function hideOldestLogo() {
        if (activeSlots.length === 0) return;
        var oldest = activeSlots.shift();
        var cellObj = cells[oldest.cellIndex];
        if (!cellObj) return;
        cellObj.el.classList.remove('is-active');
        cellObj.el.removeAttribute('title');
        setTimeout(function () {
            if (!cellObj.el.classList.contains('is-active')) {
                cellObj.slot.innerHTML = '';
            }
        }, 450);
    }

    function cycleLogos() {
        if (activeSlots.length >= maxConcurrent) {
            hideOldestLogo();
        }
        var cellIdx = pickFreeCellIndex();
        if (cellIdx !== -1) {
            var logoIdx = nextLogoCursor % logos.length;
            nextLogoCursor++;
            showLogoInCell(cellIdx, logoIdx);
        }
    }

    // Tampilkan 2 logo awal dengan jeda singkat, lalu bergantian otomatis
    cycleLogos();
    setTimeout(cycleLogos, 350);
    setInterval(cycleLogos, 2200);
})();
</script>

</body>
</html>

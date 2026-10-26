<?php
/**
 * Halaman 403 — user sudah login tapi tidak punya izin menu ini.
 */
$hideSidebar = true;
$pageName = 'error';
$currentUser = $currentUser ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — Akses Ditolak | Pradiya Portal</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%3E%3Crect%20width='32'%20height='32'%20rx='7'%20fill='%23212529'/%3E%3Cg%20transform='translate(6,6)%20scale(1.25)'%20fill='%23fff'%3E%3Cpath%20d='M10.854,7.146a.5.5,0,0,1,0,.708l-3,3a.5.5,0,0,1-.708,0l-1.5-1.5a.5.5,0,1,1,.708-.708L7.5,9.793l2.646-2.647a.5.5,0,0,1,.708,0z'/%3E%3Cpath%20d='M3.5,0a.5.5,0,0,1,.5.5V1h8V.5a.5.5,0,0,1,1,0V1h1a2,2,0,0,1,2,2v11a2,2,0,0,1-2,2H2a2,2,0,0,1-2-2V3a2,2,0,0,1,2-2h1V.5a.5.5,0,0,1,.5-.5zM1,4v10a1,1,0,0,0,1,1h12a1,1,0,0,0,1-1V4H1z'/%3E%3C/g%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="scheduling-system/public/assets/css/style.css">
</head>
<body class="login-page">
<div class="login-wrapper" style="max-width: 520px;">
    <div class="card login-card shadow">
        <div class="card-body p-4 p-md-5 text-center">
            <div class="login-logo mb-3" style="background-color:#dc3545;">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h1 class="h4 mb-2">Akses Ditolak</h1>
            <p class="text-muted mb-4">
                Akun <strong><?= htmlspecialchars($currentUser['username'] ?? '') ?></strong>
                tidak memiliki izin untuk membuka halaman ini.
                Hubungi administrator bila memang membutuhkannya.
            </p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="index.php?page=portal" class="btn btn-dark">
                    <i class="bi bi-grid-1x2"></i> Kembali ke Portal
                </a>
                <a href="index.php?page=auth&action=logout" class="btn btn-outline-secondary"
                   data-confirm="Logout dari sistem sekarang?">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="scheduling-system/public/assets/js/app.js"></script>
</body>
</html>

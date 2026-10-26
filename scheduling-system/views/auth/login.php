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
    <title>Login — Pradiya Portal</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%3E%3Crect%20width='32'%20height='32'%20rx='7'%20fill='%23212529'/%3E%3Cg%20transform='translate(6,6)%20scale(1.25)'%20fill='%23fff'%3E%3Cpath%20d='M10.854,7.146a.5.5,0,0,1,0,.708l-3,3a.5.5,0,0,1-.708,0l-1.5-1.5a.5.5,0,1,1,.708-.708L7.5,9.793l2.646-2.647a.5.5,0,0,1,.708,0z'/%3E%3Cpath%20d='M3.5,0a.5.5,0,0,1,.5.5V1h8V.5a.5.5,0,0,1,1,0V1h1a2,2,0,0,1,2,2v11a2,2,0,0,1-2,2H2a2,2,0,0,1-2-2V3a2,2,0,0,1,2-2h1V.5a.5.5,0,0,1,.5-.5zM1,4v10a1,1,0,0,0,1,1h12a1,1,0,0,0,1-1V4H1z'/%3E%3C/g%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="scheduling-system/public/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../public/assets/css/style.css') ?: '1' ?>">
</head>
<body class="login-page">

<div class="login-wrapper">
    <div class="card login-card shadow">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="login-logo mb-3">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <h1 class="h4 mb-1">Pradiya Portal</h1>
                <p class="text-muted small mb-0">Selamat datang di portal Ivan &amp; Ricka</p>
            </div>

            <?php if (!empty($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> py-2 small" role="alert">
                    <?= htmlspecialchars($_SESSION['flash_message']) ?>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 small" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="index.php?page=auth&action=login" autocomplete="off">
                <?= \Helpers\FormHelper::csrfField() ?>

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars($username) ?>" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button type="button" class="btn btn-outline-secondary" id="togglePassword"
                                tabindex="-1" aria-label="Tampilkan password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-dark w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>
        </div>
    </div>

    <p class="text-center text-muted small mt-3 mb-0">
        &copy; <?= date('Y') ?> Workforce Automation Scheduling System
    </p>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    var input = document.getElementById('password');
    var icon  = this.querySelector('i');
    var show  = input.type === 'password';
    input.type = show ? 'text' : 'password';
    icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
});
</script>

</body>
</html>

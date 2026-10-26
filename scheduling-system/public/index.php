<?php

ob_start();

/**
 * Workforce Automation Scheduling System — Front Controller
 *
 * All requests are routed through this file.
 * Dispatch is based on ?page=xxx&action=yyy parameters.
 */

set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() === 0 || $severity === E_NOTICE || $severity === E_WARNING) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// Enable error reporting
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../config/Database.php';

// Load helpers automatically
foreach (glob(__DIR__ . '/../helpers/*.php') as $helperFile) {
    require_once $helperFile;
}

// Start session for flash messages + login state
if (session_status() === PHP_SESSION_NONE) {
    // Cookie sesi login: tidak bisa dibaca JavaScript, tidak dikirim lintas situs.
    // 'secure' hanya aktif bila request benar-benar lewat HTTPS agar dev lokal tetap jalan.
    $secure = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);
    session_start();
}

// --- Routing ---

$page   = $_GET['page']   ?? 'portal';
$action = $_GET['action'] ?? 'index';

/**
 * Apakah request ini panggilan API (butuh balasan JSON)?
 *
 * Kalau API dipanggil saat sesi habis, jangan redirect ke halaman login —
 * frontend akan menerima HTML login dan melaporkan "endpoint tidak
 * mengembalikan JSON". Balas 401 JSON supaya penyebabnya jelas.
 */
$requestUri   = (string) ($_SERVER['REQUEST_URI'] ?? '');
$acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
$isApiRequest = strpos($requestUri, '/api/') !== false
    || $action === 'api'
    || strpos($acceptHeader, 'application/json') !== false;

/**
 * Balas error sebagai JSON untuk request API, lalu hentikan eksekusi.
 */
$respondApiError = static function (int $status, string $message): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode([
        'success' => false,
        'message' => $message,
        'data'    => null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
};

// Map page names to controller classes
$controllerMap = [
    'portal'             => 'Controllers\\PortalController',
    'dashboard'          => 'Controllers\\DashboardController',
    'shifts'             => 'Controllers\\ShiftController',
    'staff'              => 'Controllers\\StaffController',
    'forecast'           => 'Controllers\\ForecastController',
    'leave'              => 'Controllers\\LeaveController',
    'tl_schedule'        => 'Controllers\\TlScheduleController',
    'schedule'           => 'Controllers\\ScheduleController',
    'schedule_overview'  => 'Controllers\\ScheduleOverviewController',
    'content'            => 'Controllers\\ContentController',
    'backdate'           => 'Controllers\\BackdateController',
    'bak'                => 'Controllers\\BAKController',
    'auth'               => 'Controllers\\AuthController',
    'ffr'                => 'Controllers\\FfrController',
    'users'              => 'Controllers\\UserController',
];

/** Halaman yang boleh diakses tanpa login. */
$publicPages = ['auth'];

// Security: sanitize page name
$page = preg_replace('/[^a-z_]/', '', $page);

// --- Auth guard: semua halaman selain $publicPages butuh login ---
if (!in_array($page, $publicPages, true)) {
    // Segarkan data user dari DB: akun yang dinonaktifkan/diubah admin
    // langsung berlaku pada request berikutnya.
    \Services\AccessService::refreshSession();

    if (!\Services\AuthService::check()) {
        if ($isApiRequest) {
            $respondApiError(401, 'Sesi login Anda sudah berakhir. Muat ulang halaman (F5) lalu login kembali.');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !empty($_SERVER['QUERY_STRING'])) {
            $_SESSION['auth_redirect'] = $_SERVER['QUERY_STRING'];
        }
        header('Location: index.php?page=auth&action=login');
        exit;
    }
}

if (!isset($controllerMap[$page])) {
    if ($isApiRequest) {
        $respondApiError(404, 'Endpoint tidak ditemukan.');
    }
    http_response_code(404);
    echo '<h1>404 — Page Not Found</h1>';
    echo '<p>The requested page <strong>' . htmlspecialchars($page) . '</strong> does not exist.</p>';
    exit;
}

// --- Izin menu: user biasa hanya boleh membuka menu yang diberikan admin ---
if (!in_array($page, $publicPages, true)
    && !\Services\AccessService::can(\Services\AuthService::user(), $page)) {
    if ($isApiRequest) {
        $respondApiError(403, 'Akun Anda tidak punya akses ke halaman ini.');
    }
    http_response_code(403);
    $currentUser = \Services\AuthService::user();
    require_once __DIR__ . '/../views/errors/403.php';
    exit;
}

$controllerClass = $controllerMap[$page];

if (!class_exists($controllerClass)) {
    if ($isApiRequest) {
        $respondApiError(500, 'Controller tidak tersedia.');
    }
    http_response_code(500);
    echo '<h1>500 — Controller Not Found</h1>';
    echo '<p>Controller class <strong>' . htmlspecialchars($controllerClass) . '</strong> is missing.</p>';
    exit;
}

$controller = new $controllerClass();

if (!method_exists($controller, $action)) {
    if ($isApiRequest) {
        $respondApiError(404, 'Action tidak ditemukan.');
    }
    http_response_code(404);
    echo '<h1>404 — Action Not Found</h1>';
    echo '<p>The action <strong>' . htmlspecialchars($action) . '</strong> does not exist in this controller.</p>';
    exit;
}

// Dispatch
$controller->$action();

<?php
/**
 * Root Dispatcher
 *
 * Semua request dari https://schedulewfm.iferror.web.id/
 * akan diarahkan ke scheduling-system/public/index.php
 * tanpa mengubah URL di browser.
 */

// Path ke front controller
$frontController = __DIR__ . '/scheduling-system/public/index.php';

if (file_exists($frontController)) {
    // Forward environment variables
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $frontController;

    // Pass query string as-is
    if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '') {
        parse_str($_SERVER['QUERY_STRING'], $_GET);
    }

    require $frontController;
} else {
    http_response_code(500);
    echo '<h1>500 — System Not Found</h1>';
    echo '<p>The scheduling system is not installed. Please contact the administrator.</p>';
}

<?php
/**
 * Public HTTP Cron Endpoint for Notebook Email Reminders.
 * Can be called by cPanel Cron, GitHub Actions, or cron-job.org every 5 minutes.
 */
require_once __DIR__ . '/../config/autoload.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if (!\Services\MailService::isConfigured()) {
        echo json_encode(['success' => false, 'message' => 'SMTP not configured']);
        exit;
    }
    $res = \Services\NotebookReminderService::run();
    $payload = ['success' => true, 'time_wib' => date('Y-m-d H:i:s'), 'result' => $res];
    if (!empty($_GET['debug'])) {
        $payload['diagnose'] = \Services\NotebookReminderService::diagnose();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

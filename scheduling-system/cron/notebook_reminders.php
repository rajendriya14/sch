<?php
/**
 * Cron script untuk mengirim Email Reminder & Daily Briefing Personal Notebook.
 * Jalankan via cPanel Cron Job setiap 5 menit:
 *   php /path/to/scheduling-system/cron/notebook_reminders.php
 */

require_once __DIR__ . '/../config/autoload.php';

try {
    $res = \Services\NotebookReminderService::run();
    echo date('Y-m-d H:i:s') . ' ' . json_encode($res, JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (\Throwable $e) {
    fwrite(STDERR, date('Y-m-d H:i:s') . ' ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

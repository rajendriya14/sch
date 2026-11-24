<?php

namespace Controllers;

use Models\NotebookStickyNote;
use Models\NotebookTask;
use Services\AuthService;
use Services\MailService;

class NotebookController
{
    private function getCurrentUserId(): int
    {
        $user = AuthService::user();
        if (!$user || empty($user['id'])) {
            if ($this->isJsonRequest()) {
                $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
            }
            header('Location: index.php?page=auth&action=login');
            exit;
        }
        return (int) $user['id'];
    }

    private function isJsonRequest(): bool
    {
        $accept  = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        $reqWith = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        return strpos($accept, 'application/json') !== false
            || $reqWith === 'xmlhttprequest'
            || (isset($_GET['format']) && $_GET['format'] === 'json');
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Main Notebook View
     */
    public function index(): void
    {
        $userId      = $this->getCurrentUserId();
        $user        = AuthService::user();
        $hideSidebar = true;
        $hideNavbar  = true;
        $brandTitle  = 'Personal Notebook';
        $brandIcon   = 'bi-journal-bookmark';
        $brandUrl    = 'index.php?page=portal';
        $appLabel    = 'Personal Workspace';
        $pageName    = 'notebook';

        $tasks    = NotebookTask::allByUser($userId);
        $stickies = NotebookStickyNote::allByUser($userId);
        $settings = NotebookTask::getSettings($userId, (string) ($user['email'] ?? ''));

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/notebook/index.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * API: Get tasks for calendar/timeline range
     */
    public function apiGetTasks(): void
    {
        $userId = $this->getCurrentUserId();
        $start  = $_GET['start'] ?? date('Y-m-01', strtotime('-1 month'));
        $end    = $_GET['end']   ?? date('Y-m-t', strtotime('+2 months'));

        $tasks = NotebookTask::getByUserAndRange($userId, $start, $end);
        $this->jsonResponse(['success' => true, 'data' => $tasks]);
    }

    /**
     * API / Form: Save Task (Create or Update)
     */
    public function save(): void
    {
        $userId = $this->getCurrentUserId();

        $id               = !empty($_POST['id']) ? (int) $_POST['id'] : 0;
        $title            = trim((string) ($_POST['title'] ?? ''));
        $notes            = trim((string) ($_POST['notes'] ?? ''));
        $dueDate          = trim((string) ($_POST['due_date'] ?? ''));
        $endDate          = trim((string) ($_POST['end_date'] ?? ''));
        $dueTime          = trim((string) ($_POST['due_time'] ?? ''));
        $duration         = max(15, (int) ($_POST['duration_minutes'] ?? 60));
        $priority         = trim((string) ($_POST['priority'] ?? 'Medium'));
        $status           = trim((string) ($_POST['status'] ?? 'Todo'));
        $tag              = trim((string) ($_POST['tag'] ?? '#Personal'));
        $theme            = trim((string) ($_POST['theme'] ?? 'theme-green'));
        $color            = trim((string) ($_POST['color'] ?? '#217346'));
        $isPinned         = !empty($_POST['is_pinned']) ? 1 : 0;
        $emailReminder    = !empty($_POST['email_reminder']) ? 1 : 0;
        $reminderFreq     = trim((string) ($_POST['reminder_freq'] ?? 'once'));
        $reminderLead     = trim((string) ($_POST['reminder_lead'] ?? '1h'));
        $reminderSendTime = trim((string) ($_POST['reminder_send_time'] ?? '08:00'));
        $reminderDays     = trim((string) ($_POST['reminder_days'] ?? ''));
        $rawEmails        = $_POST['reminder_emails'] ?? '[]';

        if ($title === '') {
            $msg = 'Judul agenda / catatan tidak boleh kosong.';
            if ($this->isJsonRequest()) {
                $this->jsonResponse(['success' => false, 'message' => $msg], 400);
            }
            $_SESSION['flash_message'] = $msg;
            $_SESSION['flash_type'] = 'danger';
            header('Location: index.php?page=notebook');
            exit;
        }

        if ($dueDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $dueDate = date('Y-m-d');
        }
        if ($endDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || $endDate < $dueDate) {
            $endDate = $dueDate;
        }

        if (!in_array($priority, ['Low', 'Medium', 'High'], true)) {
            $priority = 'Medium';
        }
        if (!in_array($status, ['Todo', 'In Progress', 'Done'], true)) {
            $status = 'Todo';
        }
        if (!in_array($reminderFreq, ['once', 'workdays', 'daily', 'custom'], true)) {
            $reminderFreq = 'once';
        }

        $emailsArr = is_string($rawEmails) ? json_decode($rawEmails, true) : (is_array($rawEmails) ? $rawEmails : []);
        if (!is_array($emailsArr)) {
            $emailsArr = [];
        }
        $emailsArr = array_values(array_filter(array_map('trim', $emailsArr), static function ($em) {
            return filter_var($em, FILTER_VALIDATE_EMAIL) !== false;
        }));

        $data = [
            'user_id'            => $userId,
            'title'              => $title,
            'notes'              => $notes !== '' ? $notes : null,
            'due_date'           => $dueDate,
            'end_date'           => $endDate,
            'due_time'           => $dueTime !== '' ? $dueTime : null,
            'duration_minutes'   => $duration,
            'priority'           => $priority,
            'status'             => $status,
            'tag'                => $tag !== '' ? $tag : '#Personal',
            'theme'              => $theme !== '' ? $theme : 'theme-green',
            'color'              => $color,
            'is_pinned'          => $isPinned,
            'email_reminder'     => $emailReminder,
            'reminder_freq'      => $reminderFreq,
            'reminder_lead'      => $reminderLead,
            'reminder_send_time' => $reminderSendTime !== '' ? $reminderSendTime : null,
            'reminder_days'      => $reminderDays !== '' ? $reminderDays : null,
            'reminder_emails'    => json_encode($emailsArr, JSON_UNESCAPED_UNICODE),
        ];

        if ($id > 0) {
            $existing = NotebookTask::findByUser($id, $userId);
            if (!$existing) {
                if ($this->isJsonRequest()) {
                    $this->jsonResponse(['success' => false, 'message' => 'Task tidak ditemukan atau bukan milik Anda.'], 404);
                }
                $_SESSION['flash_message'] = 'Task tidak ditemukan.';
                $_SESSION['flash_type'] = 'danger';
                header('Location: index.php?page=notebook');
                exit;
            }
            NotebookTask::update($id, $userId, $data);
            $savedId = $id;
            $msg = 'Agenda berhasil diperbarui.';
        } else {
            $savedId = NotebookTask::create($data);
            $msg = 'Agenda baru berhasil disimpan.';
        }

        if ($this->isJsonRequest()) {
            $task = NotebookTask::findByUser($savedId, $userId);
            $this->jsonResponse(['success' => true, 'message' => $msg, 'task' => $task]);
        }

        $_SESSION['flash_message'] = $msg;
        $_SESSION['flash_type'] = 'success';
        header('Location: index.php?page=notebook');
        exit;
    }

    /**
     * API: Toggle / Update Task Status (Todo / In Progress / Done)
     */
    public function toggleStatus(): void
    {
        $userId = $this->getCurrentUserId();
        $id     = (int) ($_POST['id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));

        $existing = NotebookTask::findByUser($id, $userId);
        if (!$existing) {
            $this->jsonResponse(['success' => false, 'message' => 'Task tidak ditemukan.'], 404);
        }

        if (!in_array($status, ['Todo', 'In Progress', 'Done'], true)) {
            $status = ($existing['status'] === 'Done') ? 'Todo' : 'Done';
        }

        NotebookTask::updateStatus($id, $userId, $status);
        $this->jsonResponse(['success' => true, 'new_status' => $status]);
    }

    /**
     * API / Form: Delete Task
     */
    public function delete(): void
    {
        $userId = $this->getCurrentUserId();
        $id     = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

        $existing = NotebookTask::findByUser($id, $userId);
        if (!$existing) {
            if ($this->isJsonRequest()) {
                $this->jsonResponse(['success' => false, 'message' => 'Task tidak ditemukan.'], 404);
            }
            $_SESSION['flash_message'] = 'Task tidak ditemukan.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: index.php?page=notebook');
            exit;
        }

        NotebookTask::delete($id, $userId);
        $msg = 'Agenda berhasil dihapus.';

        if ($this->isJsonRequest()) {
            $this->jsonResponse(['success' => true, 'message' => $msg]);
        }

        $_SESSION['flash_message'] = $msg;
        $_SESSION['flash_type'] = 'success';
        header('Location: index.php?page=notebook');
        exit;
    }

    /**
     * API: Save User Email Reminder Settings
     */
    public function saveSettings(): void
    {
        $userId = $this->getCurrentUserId();
        $rawEmails = $_POST['emails'] ?? '[]';
        $emailsArr = is_string($rawEmails) ? json_decode($rawEmails, true) : (is_array($rawEmails) ? $rawEmails : []);
        if (!is_array($emailsArr)) {
            $emailsArr = [];
        }
        $emailsArr = array_values(array_filter(array_map('trim', $emailsArr), static function ($em) {
            return filter_var($em, FILTER_VALIDATE_EMAIL) !== false;
        }));

        $daysRaw = trim((string) ($_POST['digest_days'] ?? 'Sen,Sel,Rab,Kam,Jum'));
        $daysArr = array_values(array_filter(array_map('trim', explode(',', $daysRaw))));

        $rawPrefs = $_POST['preferences'] ?? '{}';
        $prefsArr = is_string($rawPrefs) ? json_decode($rawPrefs, true) : (is_array($rawPrefs) ? $rawPrefs : []);
        if (!is_array($prefsArr)) {
            $prefsArr = [];
        }

        NotebookTask::saveSettings($userId, [
            'emails'         => $emailsArr,
            'digest_enabled' => !empty($_POST['digest_enabled']),
            'digest_freq'    => trim((string) ($_POST['digest_freq'] ?? 'workdays')),
            'digest_days'    => $daysArr,
            'digest_time'    => trim((string) ($_POST['digest_time'] ?? '07:30')),
            'preferences'    => $prefsArr,
        ]);

        $settings = NotebookTask::getSettings($userId);
        $this->jsonResponse(['success' => true, 'message' => 'Pengaturan Email Reminder disimpan.', 'settings' => $settings]);
    }

    /**
     * API: Send Test Email Briefing to configured recipients
     */
    public function sendTestEmail(): void
    {
        $userId   = $this->getCurrentUserId();
        $user     = AuthService::user();
        $settings = NotebookTask::getSettings($userId, (string) ($user['email'] ?? ''));

        $recipients = $settings['emails'];
        if (empty($recipients)) {
            $this->jsonResponse(['success' => false, 'message' => 'Tambahkan minimal 1 alamat email penerima terlebih dahulu.'], 400);
        }

        $today = date('Y-m-d');
        $tasks = array_values(array_filter(NotebookTask::allByUser($userId), static function ($t) use ($today) {
            return ($t['due_date'] ?? '') <= $today && ($t['status'] ?? '') !== 'Done';
        }));

        $userName = htmlspecialchars((string) ($user['full_name'] ?? $user['username'] ?? 'User'), ENT_QUOTES, 'UTF-8');
        $itemsHtml = '';
        if (empty($tasks)) {
            $itemsHtml = '<p style="color:#6b7280;margin:0;">Belum ada agenda tertunda untuk hari ini.</p>';
        } else {
            foreach ($tasks as $t) {
                $tTitle = htmlspecialchars((string) $t['title'], ENT_QUOTES, 'UTF-8');
                $tTime  = !empty($t['due_time']) ? htmlspecialchars((string) $t['due_time'], ENT_QUOTES, 'UTF-8') : htmlspecialchars((string) $t['due_date'], ENT_QUOTES, 'UTF-8');
                $tPrio  = htmlspecialchars((string) ($t['priority'] ?? 'Medium'), ENT_QUOTES, 'UTF-8');
                $itemsHtml .= "<div style='border:1px solid #e5e7eb;border-left:4px solid #217346;border-radius:6px;padding:10px 12px;margin-bottom:8px;'>"
                    . "<strong>{$tTitle}</strong> <span style='font-size:12px;color:#6b7280;'>[{$tPrio}]</span>"
                    . "<div style='font-size:12px;color:#6b7280;'>{$tTime}</div></div>";
            }
        }

        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"></head><body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,sans-serif;color:#111827;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">'
            . '<tr><td style="background:#217346;color:#ffffff;padding:18px 24px;"><div style="font-size:12px;opacity:0.9;">Personal Notebook Assistant</div>'
            . "<div style='font-size:19px;font-weight:bold;margin-top:4px;'>Halo {$userName}, ini ringkasan agenda Anda</div></td></tr>"
            . "<tr><td style='padding:22px 24px;font-size:14px;line-height:1.6;'>{$itemsHtml}"
            . '<div style="margin-top:18px;"><a href="https://schedulewfm.iferror.web.id/index.php?page=notebook" style="display:inline-block;background:#217346;color:#fff;text-decoration:none;font-weight:bold;font-size:13px;padding:10px 18px;border-radius:6px;">Buka Personal Notebook</a></div>'
            . '</td></tr></table></body></html>';

        try {
            $mail = new MailService();
            $res  = $mail->send($recipients, '[Personal Notebook] Ringkasan Agenda Anda', $html);
            $this->jsonResponse($res, $res['success'] ? 200 : 500);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Save Sticky Note (Create or Update)
     */
    public function saveSticky(): void
    {
        $userId = $this->getCurrentUserId();
        $id     = !empty($_POST['id']) ? (int) $_POST['id'] : 0;
        $title  = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        $theme  = trim((string) ($_POST['theme'] ?? 'sticky-yellow'));
        $remTime = trim((string) ($_POST['reminder_time'] ?? ''));
        $rawEmails = $_POST['reminder_emails'] ?? '[]';

        if ($title === '' || $content === '') {
            $this->jsonResponse(['success' => false, 'message' => 'Judul dan isi sticky note tidak boleh kosong.'], 400);
        }

        if ($remTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $remTime)) {
            $remTime = '';
        }

        $emailsArr = is_string($rawEmails) ? json_decode($rawEmails, true) : (is_array($rawEmails) ? $rawEmails : []);
        if (!is_array($emailsArr)) {
            $emailsArr = [];
        }
        $emailsArr = array_values(array_filter(array_map('trim', $emailsArr), static function ($em) {
            return filter_var($em, FILTER_VALIDATE_EMAIL) !== false;
        }));

        $data = [
            'user_id'         => $userId,
            'title'           => $title,
            'content'         => $content,
            'theme'           => $theme,
            'reminder_time'   => $remTime,
            'reminder_emails' => json_encode($emailsArr, JSON_UNESCAPED_UNICODE),
        ];

        if ($id > 0) {
            $existing = NotebookStickyNote::findByUser($id, $userId);
            if (!$existing) {
                $this->jsonResponse(['success' => false, 'message' => 'Sticky note tidak ditemukan.'], 404);
            }
            NotebookStickyNote::update($id, $userId, $data);
            $savedId = $id;
            $msg = 'Sticky note diperbarui.';
        } else {
            $savedId = NotebookStickyNote::create($data);
            $msg = 'Sticky note disimpan.';
        }

        $note = NotebookStickyNote::findByUser($savedId, $userId);
        $this->jsonResponse(['success' => true, 'message' => $msg, 'sticky' => $note]);
    }

    /**
     * API: Update only reminder time from sticky note card dropdown
     */
    public function updateStickyReminder(): void
    {
        $userId = $this->getCurrentUserId();
        $id     = (int) ($_POST['id'] ?? 0);
        $time   = trim((string) ($_POST['reminder_time'] ?? ''));

        if ($time !== '' && !preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = '';
        }

        $existing = NotebookStickyNote::findByUser($id, $userId);
        if (!$existing) {
            $this->jsonResponse(['success' => false, 'message' => 'Sticky note tidak ditemukan.'], 404);
        }

        NotebookStickyNote::updateReminderTime($id, $userId, $time);
        $msg = $time !== '' ? "Reminder diatur setiap hari pukul {$time} WIB." : 'Reminder dimatikan untuk catatan ini.';
        $this->jsonResponse(['success' => true, 'message' => $msg, 'reminder_time' => $time]);
    }

    /**
     * API: Delete Sticky Note
     */
    public function deleteSticky(): void
    {
        $userId = $this->getCurrentUserId();
        $id     = (int) ($_POST['id'] ?? 0);

        $existing = NotebookStickyNote::findByUser($id, $userId);
        if (!$existing) {
            $this->jsonResponse(['success' => false, 'message' => 'Sticky note tidak ditemukan.'], 404);
        }

        NotebookStickyNote::delete($id, $userId);
        $this->jsonResponse(['success' => true, 'message' => 'Sticky note dihapus.']);
    }
}

<?php

namespace Controllers;

use Models\NotebookTask;
use Services\AuthService;

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
        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        $reqWith = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        return strpos($accept, 'application/json') !== false || $reqWith === 'xmlhttprequest' || isset($_GET['format']) && $_GET['format'] === 'json';
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
        $userId       = $this->getCurrentUserId();
        $user         = AuthService::user();
        $hideSidebar  = true;
        $brandTitle   = 'Personal Notebook';
        $brandIcon    = 'bi-journal-bookmark';
        $brandUrl     = 'index.php?page=portal';
        $appLabel     = 'Personal Workspace';
        $pageName     = 'notebook';

        $tasks = NotebookTask::allByUser($userId);

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
        $start = $_GET['start'] ?? date('Y-m-01', strtotime('-1 month'));
        $end   = $_GET['end']   ?? date('Y-m-t', strtotime('+2 months'));

        $tasks = NotebookTask::getByUserAndRange($userId, $start, $end);
        $this->jsonResponse(['success' => true, 'data' => $tasks]);
    }

    /**
     * API / Form: Save Task (Create or Update)
     */
    public function save(): void
    {
        $userId = $this->getCurrentUserId();

        $id = !empty($_POST['id']) ? (int) $_POST['id'] : 0;
        $title = trim((string) ($_POST['title'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
        $dueTime = trim((string) ($_POST['due_time'] ?? ''));
        $priority = trim((string) ($_POST['priority'] ?? 'Medium'));
        $status = trim((string) ($_POST['status'] ?? 'Todo'));
        $color = trim((string) ($_POST['color'] ?? '#2563eb'));

        if ($title === '') {
            $msg = 'Judul task tidak boleh kosong.';
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

        $validPriorities = ['Low', 'Medium', 'High'];
        if (!in_array($priority, $validPriorities, true)) {
            $priority = 'Medium';
        }

        $validStatuses = ['Todo', 'In Progress', 'Done'];
        if (!in_array($status, $validStatuses, true)) {
            $status = 'Todo';
        }

        $data = [
            'user_id'  => $userId,
            'title'    => $title,
            'notes'    => $notes !== '' ? $notes : null,
            'due_date' => $dueDate,
            'due_time' => $dueTime !== '' ? $dueTime : null,
            'priority' => $priority,
            'status'   => $status,
            'color'    => $color,
        ];

        if ($id > 0) {
            // Check ownership
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
            $msg = 'Task berhasil diperbarui.';
        } else {
            $savedId = NotebookTask::create($data);
            $msg = 'Task baru berhasil ditambahkan.';
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
     * API: Toggle Task Status (Todo <-> Done)
     */
    public function toggleStatus(): void
    {
        $userId = $this->getCurrentUserId();
        $id = (int) ($_POST['id'] ?? 0);
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
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

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
        $msg = 'Task berhasil dihapus.';

        if ($this->isJsonRequest()) {
            $this->jsonResponse(['success' => true, 'message' => $msg]);
        }

        $_SESSION['flash_message'] = $msg;
        $_SESSION['flash_type'] = 'success';
        header('Location: index.php?page=notebook');
        exit;
    }
}


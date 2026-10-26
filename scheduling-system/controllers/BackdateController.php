<?php

namespace Controllers;

use Config\Database;
use PDO;

class BackdateController
{
    public function index(): void
    {
        $pageName = 'backdate';
        $backdateFiles = [];

        try {
            $stmt = Database::getInstance()->query(
                "SELECT bd.* FROM backdate_data bd ORDER BY bd.week_start_date DESC, bd.group_name ASC"
            );
            $backdateFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {}

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/backdate/index.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Show input form (Layer 2 — paste data)
     */
    public function input(): void
    {
        $pageName = 'backdate';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/backdate/input.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function store(): void
    {
        $weekStart = $_POST['week_start'] ?? '';
        $groupName = $_POST['group_name'] ?? '';
        $fileName  = $_POST['file_name'] ?? '';
        $rawData   = $_POST['import_data'] ?? '';

        if (empty($weekStart) || empty($groupName)) {
            $_SESSION['flash_message'] = 'Week and Group are required.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: index.php?page=backdate');
            exit;
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                'INSERT INTO backdate_data (week_start_date, group_name, file_name, import_data)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE file_name = VALUES(file_name), import_data = VALUES(import_data)'
            );
            $stmt->execute([$weekStart, $groupName, $fileName, $rawData]);

            $_SESSION['flash_message'] = 'Backdate data saved successfully.';
            $_SESSION['flash_type'] = 'success';
        } catch (\PDOException $e) {
            $_SESSION['flash_message'] = 'Failed to save: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=backdate');
        exit;
    }

    public function view(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $pageName = 'backdate';
        $entry = null;

        try {
            $stmt = Database::getInstance()->prepare('SELECT * FROM backdate_data WHERE id = ?');
            $stmt->execute([$id]);
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {}

        if (!$entry) {
            $_SESSION['flash_message'] = 'Backdate entry not found.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: index.php?page=backdate');
            exit;
        }

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/backdate/view.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function delete(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare('DELETE FROM backdate_data WHERE id = ?');
            $stmt->execute([$id]);

            $_SESSION['flash_message'] = 'Backdate entry deleted.';
            $_SESSION['flash_type'] = 'success';
        } catch (\PDOException $e) {
            $_SESSION['flash_message'] = 'Failed to delete: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=backdate');
        exit;
    }
}

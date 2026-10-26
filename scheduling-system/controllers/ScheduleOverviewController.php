<?php

namespace Controllers;

use Config\Database;
use Models\ScheduleHeader;
use Models\ScheduleDetail;
use PDO;

class ScheduleOverviewController
{
    /**
     * Show all generated schedules (grouped by week)
     */
    public function index(): void
    {
        $pageName = 'schedule_overview';
        $weeks = [];

        try {
            $stmt = Database::getInstance()->query(
                'SELECT sh.*,
                        (SELECT COUNT(*) FROM schedule_details sd WHERE sd.schedule_header_id = sh.id) AS detail_count
                 FROM schedule_headers sh
                 ORDER BY sh.week_start_date DESC'
            );
            $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $weeks = [];
        }

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/schedule_overview/index.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * View details of a specific schedule
     */
    public function view(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $pageName = 'schedule_overview';

        try {
            $stmt = Database::getInstance()->prepare('SELECT * FROM schedule_headers WHERE id = ?');
            $stmt->execute([$id]);
            $header = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $header = null;
        }

        if (!$header) {
            $_SESSION['flash_message'] = 'Schedule not found.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: index.php?page=schedule_overview');
            exit;
        }

        // Build week dates
        $weekDates = [];
        $ts = strtotime($header['week_start_date']);
        for ($i = 0; $i < 7; $i++) {
            $weekDates[$i] = date('Y-m-d', strtotime("+{$i} days", $ts));
        }

        // Build employee schedule grid
        $details = ScheduleDetail::getGroupedByEmployee($id);
        $shiftCodes = [];
        try {
            $shiftStmt = Database::getInstance()->query('SELECT shift_code, category FROM shifts');
            foreach ($shiftStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $shiftCodes[$s['shift_code']] = $s['category'];
            }
        } catch (\PDOException $e) {}

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/schedule_overview/view.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Delete a schedule
     */
    public function delete(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare('DELETE FROM schedule_headers WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['flash_message'] = 'Schedule deleted.';
            $_SESSION['flash_type'] = 'success';
        } catch (\PDOException $e) {
            $_SESSION['flash_message'] = 'Failed to delete: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=schedule_overview');
        exit;
    }
}
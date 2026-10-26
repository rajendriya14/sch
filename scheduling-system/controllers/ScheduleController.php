<?php

namespace Controllers;

use Services\ScheduleService;

class ScheduleController
{
    private ScheduleService $service;

    public function __construct()
    {
        $this->service = new ScheduleService();
    }

    /**
     * Show schedule page (view or generate)
     */
    public function index(): void
    {
        $weekStart = $_GET['week'] ?? date('Y-m-d');

        // Try to load existing schedule for this week
        $data = $this->service->getForWeek($weekStart);
        $pageName = 'schedule';

        require_once __DIR__ . '/../views/layouts/header.php';
        if ($data) {
            require_once __DIR__ . '/../views/schedule/view.php';
            require_once __DIR__ . '/../views/schedule/validation.php';
        } else {
            require_once __DIR__ . '/../views/schedule/generate.php';
        }
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Generate a new schedule
     */
    public function generate(): void
    {
        $weekStart = $_POST['week_start'] ?? date('Y-m-d');

        $result = $this->service->generate($weekStart);

        if ($result['success']) {
            $_SESSION['flash_message'] = 'Schedule generated successfully! View the validation panel below.';
            $_SESSION['flash_type'] = 'success';

            // Redirect to view the generated schedule
            header('Location: index.php?page=schedule&week=' . $weekStart);
            exit;
        } else {
            $_SESSION['flash_message'] = 'Schedule generation failed: ' . ($result['error'] ?? 'Unknown error');
            $_SESSION['flash_type'] = 'danger';
            header('Location: index.php?page=schedule');
            exit;
        }
    }

    /**
     * View an existing schedule
     */
    public function view(): void
    {
        $weekStart = $_GET['week'] ?? date('Y-m-d');
        $data = $this->service->getForWeek($weekStart);
        $pageName = 'schedule';

        if (!$data) {
            $_SESSION['flash_message'] = 'No schedule found for the selected week.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: index.php?page=schedule');
            exit;
        }

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/schedule/view.php';
        require_once __DIR__ . '/../views/schedule/validation.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}

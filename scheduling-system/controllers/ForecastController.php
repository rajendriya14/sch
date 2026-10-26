<?php

namespace Controllers;

use Services\ForecastService;

class ForecastController
{
    private ForecastService $service;

    public function __construct()
    {
        $this->service = new ForecastService();
    }

    /**
     * Display forecast grid (24h × 7 days)
     */
    public function index(): void
    {
        $forecast = $this->service->getAllGrouped();
        $pageName = 'forecast';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/forecast/list.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Save forecast grid data
     */
    public function save(): void
    {
        $gridData = $_POST['forecast'] ?? [];

        if (empty($gridData)) {
            $_SESSION['flash_message'] = 'No forecast data submitted.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: index.php?page=forecast');
            exit;
        }

        $result = $this->service->saveFromGrid($gridData);

        if ($result['success']) {
            $_SESSION['flash_message'] = "Forecast updated successfully ({$result['saved']} entries).";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Errors: ' . implode(', ', $result['errors']);
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=forecast');
        exit;
    }
}

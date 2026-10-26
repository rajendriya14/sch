<?php

namespace Controllers;

class ContentController
{
    /**
     * Dashboard — default page
     */
    public function index(): void
    {
        header('Location: index.php?page=dashboard');
        exit;
    }

    /**
     * Content Moderator submenu: Scheduling
     */
    public function scheduling(): void
    {
        $pageName = 'content';
        $subPage = 'scheduling';
        header('Location: index.php?page=schedule');
        exit;
    }

    /**
     * Content Moderator submenu: Configuration
     */
    public function configuration(): void
    {
        $pageName = 'content';
        $subPage = 'configuration';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/content/configuration.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}

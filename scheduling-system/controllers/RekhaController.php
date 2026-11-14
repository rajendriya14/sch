<?php
namespace Controllers;

use Services\AccessService;
use Services\AuthService;

class RekhaController
{
    /**
     * Render atau arahkan ke Rekha Diagram Studio.
     */
    public function index(): void
    {
        $user = AuthService::user();
        if (!$user) {
            header('Location: index.php?page=auth&action=login');
            exit;
        }

        if (!AccessService::can($user, 'rekha')) {
            http_response_code(403);
            $currentUser = $user;
            require_once __DIR__ . '/../views/errors/403.php';
            exit;
        }

        // Arahkan ke file rekha.html yang membawa UI lengkap
        $v = file_exists(__DIR__ . '/../../rekha.html') ? filemtime(__DIR__ . '/../../rekha.html') : time();
        header("Location: rekha.html?v={$v}");
        exit;
    }

    /**
     * Endpoint API sesi user untuk rekha.html
     */
    public function session(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        
        $user = AuthService::user();
        if (!$user) {
            echo json_encode([
                'authenticated' => false,
                'canAccess'     => false,
                'user'          => null,
            ]);
            exit;
        }

        $canAccess = AccessService::can($user, 'rekha');

        echo json_encode([
            'authenticated' => true,
            'canAccess'     => (bool) $canAccess,
            'user'          => [
                'id'       => (int) ($user['id'] ?? 0),
                'name'     => $user['name'] ?? $user['username'] ?? 'User',
                'username' => $user['username'] ?? '',
                'role'     => $user['role'] ?? 'user',
            ],
        ]);
        exit;
    }
}

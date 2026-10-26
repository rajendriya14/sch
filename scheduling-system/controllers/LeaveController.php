<?php

namespace Controllers;

use Services\LeaveService;

class LeaveController
{
    private LeaveService $service;

    public function __construct()
    {
        $this->service = new LeaveService();
    }

    /**
     * List all leave requests
     */
    public function index(): void
    {
        $leaves = $this->service->getAll();
        $pageName = 'leave';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/leave/list.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Show create form
     */
    public function create(): void
    {
        $pageName = 'leave';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/leave/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Store new leave request
     */
    public function store(): void
    {
        $data = [
            'email'        => $_POST['email'] ?? '',
            'request_date' => $_POST['request_date'] ?? '',
            'reason'       => $_POST['reason'] ?? '',
        ];

        $result = $this->service->create($data);

        if ($result['success']) {
            $_SESSION['flash_message'] = 'Leave request submitted and approved.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Error: ' . implode(', ', $result['errors']);
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=leave');
        exit;
    }

    /**
     * Approve a leave request
     */
    public function approve(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($this->service->approve($id)) {
            $_SESSION['flash_message'] = 'Leave request approved.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to approve leave request.';
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=leave');
        exit;
    }

    /**
     * Reject a leave request
     */
    public function reject(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($this->service->reject($id)) {
            $_SESSION['flash_message'] = 'Leave request rejected.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to reject leave request.';
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=leave');
        exit;
    }

    /**
     * Delete a leave request
     */
    public function delete(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($this->service->delete($id)) {
            $_SESSION['flash_message'] = 'Leave request deleted.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to delete leave request.';
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=leave');
        exit;
    }
}

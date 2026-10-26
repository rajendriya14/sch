<?php

namespace Controllers;

use Services\StaffService;

class StaffController
{
    private StaffService $service;

    public function __construct()
    {
        $this->service = new StaffService();
    }

    /**
     * Display all employees (grouped by compo)
     */
    public function index(): void
    {
        $grouped = $this->service->getAllGroupedByCompo();
        $pageName = 'staff';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/staff/list.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Show import/bulk upload form
     */
    public function create(): void
    {
        $pageName = 'staff';
        $importColumns = $this->service->getImportColumns();

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/staff/import.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Process bulk import from pasted text
     */
    public function store(): void
    {
        $rawText = $_POST['import_data'] ?? '';

        if (empty(trim($rawText))) {
            $_SESSION['flash_message'] = 'No data provided. Please paste your data.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: index.php?page=staff&action=create');
            exit;
        }

        // Parse the tab-separated data
        $parsed = $this->service->parseImportData($rawText);
        if (!$parsed['success']) {
            $_SESSION['flash_message'] = 'Parse error: ' . implode(', ', $parsed['errors']);
            $_SESSION['flash_type'] = 'danger';
            header('Location: index.php?page=staff&action=create');
            exit;
        }

        // Bulk import
        $result = $this->service->bulkImport($parsed['rows']);

        if ($result['success']) {
            $msg = $result['created'] . ' employee(s) imported successfully.';
            if (!empty($result['errors'])) {
                $msg .= ' Warnings: ' . implode(' | ', array_slice($result['errors'], 0, 5));
                if (count($result['errors']) > 5) {
                    $msg .= ' (+' . (count($result['errors']) - 5) . ' more)';
                }
            }
            $_SESSION['flash_message'] = $msg;
            $_SESSION['flash_type'] = $result['created'] > 0 ? 'success' : 'warning';
        } else {
            $_SESSION['flash_message'] = 'Import failed: ' . implode(', ', $result['errors']);
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=staff');
        exit;
    }

    /**
     * Show single employee edit form
     */
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $employee = $this->service->getById($id);

        if (!$employee) {
            $_SESSION['flash_message'] = 'Employee not found.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: index.php?page=staff');
            exit;
        }

        $pageName = 'staff';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/staff/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Update employee
     */
    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'employee_id'       => $_POST['employee_id'] ?? '',
            'name'              => $_POST['name'] ?? '',
            'email'             => $_POST['email'] ?? '',
            'gender'            => $_POST['gender'] ?? '',
            'position'          => $_POST['position'] ?? '',
            'lob'               => $_POST['lob'] ?? '',
            'team_leader'       => $_POST['team_leader'] ?? '',
            'team_leader_email' => $_POST['team_leader_email'] ?? '',
            'operational_mgr'   => $_POST['operational_mgr'] ?? '',
            'domain'            => $_POST['domain'] ?? '',
            'compo_month'       => $_POST['compo_month'] ?? '',
            'compo_year'        => $_POST['compo_year'] ?? '',
            'employment_status' => $_POST['employment_status'] ?? 'Active',
            'join_date'         => $_POST['join_date'] ?? '',
        ];

        $result = $this->service->update($id, $data);

        if ($result['success']) {
            $_SESSION['flash_message'] = 'Employee updated successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Error: ' . implode(', ', $result['errors']);
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=staff');
        exit;
    }

    /**
     * AJAX lookup employee by email (for leave form auto-fill)
     */
    public function lookup(): void
    {
        header('Content-Type: application/json');

        $email = $_GET['email'] ?? '';
        if (empty($email)) {
            echo json_encode(['found' => false]);
            exit;
        }

        $employee = \Models\Employee::findByEmail($email);
        if ($employee) {
            echo json_encode([
                'found'  => true,
                'id'     => $employee['id'],
                'name'   => $employee['name'],
                'gender' => $employee['gender'],
            ]);
        } else {
            echo json_encode(['found' => false]);
        }
        exit;
    }

    /**
     * Delete employee
     */
    public function delete(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($this->service->delete($id)) {
            $_SESSION['flash_message'] = 'Employee deleted successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to delete employee.';
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=staff');
        exit;
    }

    /**
     * AJAX check if compo (month+year) already exists in DB
     */
    public function checkCompo(): void
    {
        header('Content-Type: application/json');

        $month = $_GET['month'] ?? '';
        $year  = $_GET['year'] ?? '';

        if (empty($month) || empty($year)) {
            echo json_encode(['exists' => false]);
            exit;
        }

        $exists = $this->service->compoExists($month, $year);
        echo json_encode(['exists' => $exists]);
        exit;
    }

    /**
     * Delete all employees for a given compo month+year
     */
    public function deleteCompo(): void
    {
        $month = $_POST['month'] ?? '';
        $year  = $_POST['year'] ?? '';

        if (empty($month) || empty($year)) {
            $_SESSION['flash_message'] = 'Month and Year are required.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: index.php?page=staff');
            exit;
        }

        $result = $this->service->deleteByCompo($month, $year);

        if ($result['success']) {
            $_SESSION['flash_message'] = $result['deleted'] . ' employee(s) deleted for ' . $month . ' ' . $year . '.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Delete failed: ' . ($result['error'] ?? 'Unknown error');
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=staff');
        exit;
    }
}

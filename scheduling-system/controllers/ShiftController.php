<?php

namespace Controllers;

use Services\ShiftService;

class ShiftController
{
    private ShiftService $service;

    public function __construct()
    {
        $this->service = new ShiftService();
    }

    /**
     * List all shifts
     */
    public function index(): void
    {
        $shifts = $this->service->getAll();
        $femaleRestricted = $this->service->getFemaleRestrictedCodes();
        $inactiveCodes = $this->service->getInactiveCodes();
        $pageName = 'shifts';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/shifts/list.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Show create form
     */
    public function create(): void
    {
        $pageName = 'shifts';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/shifts/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Store new shift
     */
    public function store(): void
    {
        $data = [
            'shift_code'  => $_POST['shift_code'] ?? '',
            'start_time'  => $_POST['start_time'] ?? '',
            'end_time'    => $_POST['end_time'] ?? '',
            'category'    => $_POST['category'] ?? '',
            'description' => trim($_POST['description'] ?? '') ?: null,
        ];

        $result = $this->service->create($data);

        // Save female restricted status for new shift
        if ($result['success']) {
            $isRestricted = isset($_POST['female_restricted']) && $_POST['female_restricted'] === '1';
            \Models\Shift::setFemaleRestricted($data['shift_code'], $isRestricted);
        }

        if ($result['success']) {
            $_SESSION['flash_message'] = 'Shift created successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Error: ' . implode(', ', $result['errors']);
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=shifts');
        exit;
    }

    /**
     * Show edit form
     */
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $shift = $this->service->getById($id);

        if (!$shift) {
            $_SESSION['flash_message'] = 'Shift not found.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: index.php?page=shifts');
            exit;
        }

        // Check if this shift is female restricted
        $restrictedCodes = \Models\Shift::getFemaleRestrictedCodes();
        $isFemaleRestricted = in_array($shift['shift_code'], $restrictedCodes);

        $pageName = 'shifts';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/shifts/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Update shift
     */
    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'shift_code'  => $_POST['shift_code'] ?? '',
            'start_time'  => $_POST['start_time'] ?? '',
            'end_time'    => $_POST['end_time'] ?? '',
            'category'    => $_POST['category'] ?? '',
            'description' => trim($_POST['description'] ?? '') ?: null,
        ];

        $result = $this->service->update($id, $data);

        // Save female restricted status
        if ($result['success']) {
            $isRestricted = isset($_POST['female_restricted']) && $_POST['female_restricted'] === '1';
            \Models\Shift::setFemaleRestricted($data['shift_code'], $isRestricted);
        }

        if ($result['success']) {
            $_SESSION['flash_message'] = 'Shift updated successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Error: ' . implode(', ', $result['errors']);
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=shifts');
        exit;
    }

    /**
     * Delete shift
     */
    public function delete(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($this->service->delete($id)) {
            $_SESSION['flash_message'] = 'Shift deleted successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to delete shift.';
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=shifts');
        exit;
    }

    /**
     * Restore the default shift list (16 regular shifts + 10 leave types).
     */
    public function restoreDefaults(): void
    {
        $added = $this->service->restoreDefaults();

        $_SESSION['flash_message'] = $added > 0
            ? $added . ' shift bawaan berhasil ditambahkan.'
            : 'Semua shift bawaan sudah ada — tidak ada yang ditambahkan.';
        $_SESSION['flash_type'] = $added > 0 ? 'success' : 'info';

        header('Location: index.php?page=shifts');
        exit;
    }

    /**
     * Toggle shift active/inactive status — AJAX
     */
    public function toggle(): void
    {
        header('Content-Type: application/json');

        $code = $_POST['code'] ?? '';
        $active = ($_POST['active'] ?? '') === 'true' || ($_POST['active'] ?? '') === '1';
        if (empty($code)) {
            echo json_encode(['success' => false, 'error' => 'Shift code required.']);
            exit;
        }

        $result = $this->service->toggleActive($code, $active);
        echo json_encode($result);
        exit;
    }

    /**
     * Save all shift active states at once (bulk save)
     */
    public function saveToggle(): void
    {
        // Receive array: status[shift_code] = 1|0 (only checked ones are sent)
        $statuses = $_POST['status'] ?? [];

        // Get all shift codes from DB
        $allShifts = \Models\Shift::getAll();
        if (empty($allShifts)) {
            $_SESSION['flash_message'] = 'Tidak ada shift ditemukan.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: index.php?page=shifts');
            exit;
        }

        $saved = 0;
        foreach ($allShifts as $shift) {
            $code = $shift['shift_code'];
            // Checked = present in POST status => active. Unchecked = absent => inactive.
            $isActive = isset($statuses[$code]) && ($statuses[$code] === '1' || $statuses[$code] === 'on' || $statuses[$code] === 'true');
            $this->service->toggleActive($code, $isActive);
            $saved++;
        }

        $_SESSION['flash_message'] = $saved . ' shift berhasil disimpan.';
        $_SESSION['flash_type'] = 'success';
        header('Location: index.php?page=shifts');
        exit;
    }
}

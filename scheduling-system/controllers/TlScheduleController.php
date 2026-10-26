<?php

namespace Controllers;

use Services\TlScheduleService;

class TlScheduleController
{
    private TlScheduleService $service;

    public function __construct()
    {
        $this->service = new TlScheduleService();
    }

    /**
     * List all TL schedules (grouped by week)
     */
    public function index(): void
    {
        $weeks = $this->service->getAllGroupedByWeek();
        $pageName = 'tl_schedule';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/tl_schedule/list.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Show create form
     */
    public function create(): void
    {
        $pageName = 'tl_schedule';
        $teamLeaders = $this->service->getTeamLeaders();
        $shiftCodes = $this->service->getShiftCodes();

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/tl_schedule/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Store new TL schedule entry
     */
    public function store(): void
    {
        $data = [
            'team_leader' => $_POST['team_leader'] ?? '',
            'day_of_week' => $_POST['day_of_week'] ?? '',
            'shift_code'  => $_POST['shift_code'] ?? '',
        ];

        $result = $this->service->save($data);

        if ($result['success']) {
            $_SESSION['flash_message'] = 'TL schedule entry saved successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Error: ' . implode(', ', $result['errors']);
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=tl_schedule');
        exit;
    }

    /**
     * Show edit form
     */
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $entry = $this->service->getById($id);

        if (!$entry) {
            $_SESSION['flash_message'] = 'TL schedule entry not found.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: index.php?page=tl_schedule');
            exit;
        }

        $pageName = 'tl_schedule';
        $teamLeaders = $this->service->getTeamLeaders();
        $shiftCodes = $this->service->getShiftCodes();

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/tl_schedule/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Update TL schedule entry
     */
    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'team_leader' => $_POST['team_leader'] ?? '',
            'day_of_week' => $_POST['day_of_week'] ?? '',
            'shift_code'  => $_POST['shift_code'] ?? '',
        ];

        // Delete old entry and re-insert (simplest approach with upsert)
        $result = $this->service->save($data);

        if ($result['success']) {
            $_SESSION['flash_message'] = 'TL schedule entry updated successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Error: ' . implode(', ', $result['errors']);
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=tl_schedule');
        exit;
    }

    /**
     * Delete TL schedule entry
     */
    public function delete(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($this->service->delete($id)) {
            $_SESSION['flash_message'] = 'TL schedule entry deleted.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to delete TL schedule entry.';
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=tl_schedule');
        exit;
    }

    /**
     * Delete all entries for a week
     */
    public function deleteWeek(): void
    {
        $week = $_POST['week'] ?? '';
        if (empty($week)) {
            $_SESSION['flash_message'] = 'Week is required.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: index.php?page=tl_schedule');
            exit;
        }

        if ($this->service->deleteWeek($week)) {
            $_SESSION['flash_message'] = "TL schedule entries for week starting {$week} deleted.";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to delete week entries.';
            $_SESSION['flash_type'] = 'danger';
        }
        header('Location: index.php?page=tl_schedule');
        exit;
    }

    /**
     * Overview for a specific week
     */
    public function overview(): void
    {
        $weekStart = $_GET['week'] ?? '';
        $pageName = 'tl_schedule';

        if (empty($weekStart)) {
            $_SESSION['flash_message'] = 'Week is required.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: index.php?page=tl_schedule');
            exit;
        }

        $entries = $this->service->getByWeek($weekStart);

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/tl_schedule/overview.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Show bulk import page
     */
    public function input(): void
    {
        $pageName = 'tl_schedule';
        $shiftCodes = $this->service->getShiftCodes();

        // Fetch active regular shifts for forecast grid rows
        $allShifts = \Models\Shift::getAll();
        $inactiveCodes = \Models\Shift::getInactiveCodes();
        $activeShifts = array_values(array_filter($allShifts, function($s) use ($inactiveCodes) {
            return $s['category'] !== 'Leave'
                && !in_array($s['shift_code'], $inactiveCodes)
                && ($s['is_active'] ?? 1) == 1;
        }));

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/tl_schedule/input.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Process bulk import
     */
    public function import(): void
    {
        $rawText = $_POST['import_data'] ?? '';
        $weekStart = $_POST['week_start'] ?? date('Y-m-d');

        if (empty(trim($rawText))) {
            $_SESSION['flash_message'] = 'No data provided.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: index.php?page=tl_schedule&action=input');
            exit;
        }

        $result = $this->service->importBulk($rawText, $weekStart);

        if ($result['success']) {
            $msg = $result['saved'] . ' schedule entry(ies) saved.';
            if (!empty($result['errors'])) {
                $msg .= ' Warnings: ' . implode(' | ', $result['errors']);
            }
            $_SESSION['flash_message'] = $msg;
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Import failed: ' . implode(' | ', $result['errors']);
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=tl_schedule');
        exit;
    }

    /**
     * Run automation scheduling using the SchedulingEngine
     */
    public function automation(): void
    {
        $weekStart = $_POST['week_start'] ?? date('Y-m-d');
        $domain    = $_POST['domain'] ?? '';

        try {
            $engine = new \Services\SchedulingEngine();
            $result = $engine->generate($weekStart, $domain ?: null);

            if (!empty($result['schedule'])) {
                // Save to database
                $headerId = \Models\ScheduleHeader::create([
                    'week_start_date' => $weekStart,
                    'status'          => 'Draft',
                ]);

                $details = [];
                foreach ($result['schedule'] as $empId => $days) {
                    $employee = \Models\Employee::find($empId);
                    if (!$employee) continue;
                    foreach ($days as $d => $shiftCode) {
                        $shiftCode = $shiftCode ?: 'OFF';
                        $details[] = [
                            'schedule_header_id' => $headerId,
                            'employee_id'        => $empId,
                            'day_of_week'        => $d,
                            'shift_code'         => $shiftCode,
                            'work_date'          => $result['weekDates'][$d],
                        ];
                    }
                }

                \Models\ScheduleDetail::bulkCreate($details);

                $msg = 'Schedule berhasil dibuat';
                if ($domain) {
                    $msg .= ' untuk domain ' . $domain . ' (' . count($result['schedule']) . ' staff)';
                }
                $_SESSION['flash_message'] = $msg;
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = 'Schedule gagal dibuat: tidak ada hasil.';
                $_SESSION['flash_type'] = 'danger';
            }
        } catch (\Exception $e) {
            $_SESSION['flash_message'] = 'Schedule gagal dibuat: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: index.php?page=schedule_overview');
        exit;
    }
}

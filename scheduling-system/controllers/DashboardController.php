<?php

namespace Controllers;

use Models\Employee;
use Models\Shift;
use Models\ForecastRequirement;
use Models\LeaveRequest;
use Models\ScheduleHeader;

class DashboardController
{
    /**
     * Show dashboard with summary stats
     */
    public function index(): void
    {
        $employeeCount = count(Employee::allActive());
        $shiftCount = count(Shift::getAll());
        $leavePending = count(array_filter(LeaveRequest::all(), fn($r) => $r['status'] === 'Pending'));
        $latestSchedule = ScheduleHeader::getLatest();
        $pageName = 'dashboard';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/dashboard/index.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}

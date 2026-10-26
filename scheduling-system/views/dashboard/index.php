<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
        <p class="text-muted">Workforce Automation Scheduling System — Overview</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-3 col-lg-4 col-md-6 col-12">
        <a href="index.php?page=staff" class="dashboard-card">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="dashboard-icon mb-2">
                        <i class="bi bi-people"></i>
                    </div>
                    <h3 class="card-title"><?= $employeeCount ?? 0 ?></h3>
                    <p class="card-text text-muted">Active Employees</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-xl-3 col-lg-4 col-md-6 col-12">
        <a href="index.php?page=shifts" class="dashboard-card">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="dashboard-icon mb-2">
                        <i class="bi bi-clock"></i>
                    </div>
                    <h3 class="card-title"><?= $shiftCount ?? 0 ?></h3>
                    <p class="card-text text-muted">Shift Definitions</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-xl-3 col-lg-4 col-md-6 col-12">
        <a href="index.php?page=leave" class="dashboard-card">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="dashboard-icon mb-2">
                        <i class="bi bi-file-text"></i>
                    </div>
                    <h3 class="card-title"><?= $leavePending ?? 0 ?></h3>
                    <p class="card-text text-muted">Pending Leave Requests</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-xl-3 col-lg-4 col-md-6 col-12">
        <a href="index.php?page=schedule" class="dashboard-card">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="dashboard-icon mb-2">
                        <i class="bi bi-table"></i>
                    </div>
                    <h3 class="card-title">
                        <?= $latestSchedule ? date('d M', strtotime($latestSchedule['week_start_date'])) : '—' ?>
                    </h3>
                    <p class="card-text text-muted">Latest Schedule</p>
                </div>
            </div>
        </a>
    </div>
</div>

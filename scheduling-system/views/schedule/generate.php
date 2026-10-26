<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-table"></i> Generate Schedule</h2>
        <p class="text-muted mb-0">Click the button below to auto-generate a weekly schedule based on current master data and forecast requirements.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-xl-6 col-lg-8 col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-calendar-week" style="font-size: 3rem; opacity: 0.5;"></i>
                </div>

                <form method="post" action="index.php?page=schedule&action=generate">
                    <div class="mb-3">
                        <label for="week_start" class="form-label">Select Week Starting:</label>
                        <input type="date" class="form-control text-center" id="week_start" name="week_start"
                               value="<?= date('Y-m-d', strtotime('last Monday')) ?>"
                               required>
                    </div>

                    <p class="text-muted small mb-3">
                        The schedule will be generated for <strong>Monday through Sunday</strong>
                        of the selected week.
                    </p>

                    <button type="submit" class="btn btn-lg btn-success" id="btnGenerateSchedule">
                        <i class="bi bi-gear"></i> Generate Schedule
                    </button>
                </form>

                <div class="schedule-loading mt-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Generating...</span>
                    </div>
                    <p class="mt-2 text-muted">Generating schedule. This may take a moment...</p>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-info-circle"></i> Prerequisites
            </div>
            <div class="card-body">
                <p>Before generating a schedule, ensure the following data is in place:</p>
                <ul class="mb-0">
                    <li><strong>Shift Master</strong> — all shift definitions (pre-seeded)</li>
                    <li><strong>Staff Master</strong> — at least 1 active employee</li>
                    <li><strong>Forecast Requirements</strong> — staffing needs per hour</li>
                    <li><strong>Leave Requests</strong> (optional) — approved leaves will be respected</li>
                    <li><strong>TL Schedules</strong> (optional) — for team leader meeting rules</li>
                </ul>
            </div>
        </div>
    </div>
</div>

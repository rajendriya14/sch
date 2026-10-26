<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2><i class="bi bi-graph-up"></i> Forecast Requirements</h2>
        <p class="text-muted mb-0">Set the required number of agents for each hourly interval, Monday through Sunday.</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="index.php?page=forecast&action=save">
            <div class="table-responsive">
                <table class="table table-bordered table-sm forecast-grid">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center">Hour</th>
                            <th class="text-center">Monday</th>
                            <th class="text-center">Tuesday</th>
                            <th class="text-center">Wednesday</th>
                            <th class="text-center">Thursday</th>
                            <th class="text-center">Friday</th>
                            <th class="text-center">Saturday</th>
                            <th class="text-center">Sunday</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($hour = 0; $hour < 24; $hour++): ?>
                            <tr data-hour="<?= $hour ?>">
                                <td class="text-center fw-bold"><?= sprintf('%02d:00', $hour) ?></td>
                                <?php for ($day = 0; $day < 7; $day++): ?>
                                    <td class="text-center">
                                        <input type="number"
                                               name="forecast[<?= $hour ?>][<?= $day ?>]"
                                               value="<?= (int)($forecast[$day][$hour] ?? 0) ?>"
                                               min="0" max="999"
                                               class="form-control form-control-sm d-inline-block">
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Save Forecast
                </button>
                <a href="index.php?page=dashboard" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

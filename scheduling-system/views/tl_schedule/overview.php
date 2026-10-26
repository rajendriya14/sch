<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-eye"></i> TL Schedule Overview</h2>
        <p class="text-muted">Week starting <strong><?= htmlspecialchars($_GET['week'] ?? '') ?></strong></p>
    </div>
    <div class="col text-end">
        <a href="index.php?page=tl_schedule" class="btn btn-outline-secondary">
            <i class="bi bi-list"></i> Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm schedule-grid">
                <thead class="table-dark">
                    <tr>
                        <th class="text-start">Team Leader</th>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                        <th>Sunday</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                        // Group entries by team_leader
                        $grouped = [];
                        foreach ($entries as $e) {
                            $tl = $e['team_leader'];
                            $grouped[$tl]['name'] = $tl;
                            $grouped[$tl]['shifts'][(int)$e['day_of_week']] = $e;
                        }
                    ?>
                    <?php if (empty($grouped)): ?>
                        <tr><td colspan="8" class="text-center text-muted">No entries for this week.</td></tr>
                    <?php else: ?>
                        <?php foreach ($grouped as $tl => $data): ?>
                            <tr>
                                <td class="text-start"><strong><?= htmlspecialchars($tl) ?></strong></td>
                                <?php for ($d = 0; $d < 7; $d++): ?>
                                    <?php if (isset($data['shifts'][$d])): ?>
                                        <?php $e = $data['shifts'][$d]; ?>
                                        <td>
                                            <span class="badge bg-dark"><?= htmlspecialchars($e['shift_code']) ?></span><br>
                                            <small><?= date('H:i', strtotime($e['start_time'])) ?>-<?= date('H:i', strtotime($e['end_time'])) ?></small>
                                        </td>
                                    <?php else: ?>
                                        <td class="text-muted">—</td>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

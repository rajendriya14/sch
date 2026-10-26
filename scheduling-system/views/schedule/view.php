<?php
// Build shift lookup for CSS classes (must be before the template)
$shiftsByCode = [];
foreach (\Models\Shift::getAll() as $s) {
    $shiftsByCode[$s['shift_code']] = $s;
}
?>

<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-table"></i> Weekly Schedule</h2>
        <p class="text-muted">
            Week starting <strong><?= date('d M Y', strtotime($data['header']['week_start_date'])) ?></strong>
            &middot; Generated <?= date('d M Y H:i', strtotime($data['header']['generated_at'])) ?>
            &middot; Status: <span class="badge bg-info"><?= $data['header']['status'] ?></span>
        </p>
    </div>
    <div class="col text-end">
        <a href="index.php?page=schedule&action=generate" class="btn btn-success">
            <i class="bi bi-gear"></i> Regenerate
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm schedule-grid">
                <thead class="table-dark">
                    <tr>
                        <th class="text-start">Employee</th>
                        <th>Monday<br><small><?= date('d M', strtotime($data['weekDates'][0])) ?></small></th>
                        <th>Tuesday<br><small><?= date('d M', strtotime($data['weekDates'][1])) ?></small></th>
                        <th>Wednesday<br><small><?= date('d M', strtotime($data['weekDates'][2])) ?></small></th>
                        <th>Thursday<br><small><?= date('d M', strtotime($data['weekDates'][3])) ?></small></th>
                        <th>Friday<br><small><?= date('d M', strtotime($data['weekDates'][4])) ?></small></th>
                        <th>Saturday<br><small><?= date('d M', strtotime($data['weekDates'][5])) ?></small></th>
                        <th>Sunday<br><small><?= date('d M', strtotime($data['weekDates'][6])) ?></small></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['details'])): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">No schedule data available.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['details'] as $empDbId => $empData): ?>
                            <tr>
                                <td class="text-start">
                                    <strong><?= htmlspecialchars($empData['emp_id']) ?></strong><br>
                                    <small><?= htmlspecialchars($empData['name']) ?></small>
                                    <?php if ($empData['gender'] === 'Female'): ?>
                                        <span class="badge bg-danger" style="font-size:0.6rem;">F</span>
                                    <?php endif; ?>
                                </td>
                                <?php for ($d = 0; $d < 7; $d++): ?>
                                    <?php
                                        $shiftCode = $empData['shifts'][$d] ?? '—';
                                        $shiftClass = 'shift-cell';
                                        if ($shiftCode === 'OFF' || $shiftCode === '—') {
                                            $shiftClass .= ' shift-OFF';
                                        } elseif (isset($shiftsByCode[$shiftCode])) {
                                            $cat = $shiftsByCode[$shiftCode]['category'] ?? '';
                                            $shiftClass .= ' shift-' . $cat;
                                        }
                                    ?>
                                    <td>
                                        <span class="<?= $shiftClass ?>">
                                            <?= htmlspecialchars($shiftCode) ?>
                                        </span>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

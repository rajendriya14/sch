<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2><i class="bi bi-clock"></i> Shift Master</h2>
        <p class="text-muted mb-0">Centang shift/leave yang AKTIF diproses — yang tidak dicentang akan di-skip oleh scheduling engine.</p>
    </div>
    <div>
        <a href="index.php?page=shifts&action=restoreDefaults" class="btn btn-outline-secondary"
           data-confirm="Tambahkan shift bawaan yang belum ada (16 shift regular + 10 tipe leave)? Shift yang sudah ada tidak diubah.">
            <i class="bi bi-arrow-repeat"></i> Restore Default Shifts
        </a>
        <a href="index.php?page=shifts&action=create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add Shift
        </a>
    </div>
</div>

<form method="post" action="index.php?page=shifts&action=saveToggle" id="shiftSaveForm">
<?php
    $inactiveCodes = $inactiveCodes ?? \Models\Shift::getInactiveCodes();
    $regShifts = array_filter($shifts, fn($s) => !in_array($s['category'], ['Leave']));
    $leaveTypes = array_filter($shifts, fn($s) => $s['category'] === 'Leave');
    $badgeMap = ['Morning' => 'bg-success', 'Swing' => 'bg-warning text-dark', 'Night' => 'bg-info text-dark', 'Leave' => 'bg-secondary'];
?>

<!-- Regular Shifts Section -->
<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <i class="bi bi-clock"></i> Regular Shifts
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Category</th>
                        <th>Restricted</th>
                        <th>Proses</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($regShifts)): ?>
                        <tr><td colspan="7" class="text-center text-muted">No shifts defined.</td></tr>
                    <?php else: ?>
                        <?php foreach ($regShifts as $shift): ?>
                            <?php
                                $isRestricted = in_array($shift['shift_code'], $femaleRestricted ?? []);
                                $isInactive = in_array($shift['shift_code'], $inactiveCodes);
                                $badgeClass = $badgeMap[$shift['category']] ?? 'bg-secondary';
                            ?>
                            <tr class="<?= $isInactive ? 'table-secondary text-muted' : '' ?>">
                                <td><strong><?= htmlspecialchars($shift['shift_code']) ?></strong></td>
                                <td><?= date('H:i', strtotime($shift['start_time'])) ?></td>
                                <td><?= date('H:i', strtotime($shift['end_time'])) ?></td>
                                <td><span class="badge <?= $badgeClass ?>"><?= $shift['category'] ?></span></td>
                                <td><?= $isRestricted ? '<span class="badge bg-danger">Female Restricted</span>' : '<span class="text-muted">—</span>' ?></td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center mb-0">
                                        <input type="checkbox" class="form-check-input shift-toggle"
                                               name="status[<?= htmlspecialchars($shift['shift_code']) ?>]"
                                               value="1"
                                               id="chk_<?= htmlspecialchars($shift['shift_code']) ?>"
                                               <?= $isInactive ? '' : 'checked' ?>
                                               style="cursor:pointer;">
                                    </div>
                                </td>
                                <td>
                                    <a href="index.php?page=shifts&action=edit&id=<?= $shift['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="index.php?page=shifts&action=delete&id=<?= $shift['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete <?= htmlspecialchars($shift['shift_code']) ?>?"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Leave Types Section -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <i class="bi bi-journal-text"></i> Leave Types — centang yang akan diproses
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Proses</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaveTypes)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No leave types yet. <a href="index.php?page=shifts&action=create">Add Leave Type</a></td></tr>
                    <?php else: ?>
                        <?php foreach ($leaveTypes as $shift): ?>
                            <?php $isInactive = in_array($shift['shift_code'], $inactiveCodes); ?>
                            <tr class="<?= $isInactive ? 'table-secondary text-muted' : '' ?>">
                                <td><strong><?= htmlspecialchars($shift['shift_code']) ?></strong></td>
                                <td><small><?= htmlspecialchars($shift['description'] ?? '—') ?></small></td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center mb-0">
                                        <input type="checkbox" class="form-check-input shift-toggle"
                                               name="status[<?= htmlspecialchars($shift['shift_code']) ?>]"
                                               value="1"
                                               id="chkLeave_<?= htmlspecialchars($shift['shift_code']) ?>"
                                               <?= $isInactive ? '' : 'checked' ?>
                                               style="cursor:pointer;">
                                    </div>
                                </td>
                                <td>
                                    <a href="index.php?page=shifts&action=edit&id=<?= $shift['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="index.php?page=shifts&action=delete&id=<?= $shift['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete <?= htmlspecialchars($shift['shift_code']) ?>?"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Save</button>
    <a href="index.php?page=shifts" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancel</a>
</div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Visual toggle only (saving happens on Save button submit)
    document.querySelectorAll('.shift-toggle').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            row.classList.toggle('table-secondary');
            row.classList.toggle('text-muted');
        });
    });
});
</script>

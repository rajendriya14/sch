<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2><i class="bi bi-calendar-range"></i> Backdate Schedule</h2>
    </div>
    <div>
        <a href="index.php?page=backdate&action=input" class="btn btn-primary">
            <i class="bi bi-upload"></i> Input Backdate
        </a>
    </div>
</div>

<?php if (empty($backdateFiles)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-calendar-range" style="font-size:3rem;opacity:0.3;"></i>
            <p class="mt-3 text-muted">No backdate data yet.</p>
            <a href="index.php?page=backdate&action=input" class="btn btn-primary">
                <i class="bi bi-upload"></i> Input Now
            </a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($backdateFiles as $bf): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-calendar-week"></i>
                    <strong><?= htmlspecialchars($bf['file_name'] ?: 'Week ' . $bf['week_start_date'] . ' - ' . $bf['group_name']) ?></strong>
                </span>
            </div>
            <div class="card-body">
                <a href="index.php?page=backdate&action=view&id=<?= $bf['id'] ?>" class="btn btn-sm btn-outline-info">
                    <i class="bi bi-eye"></i> View
                </a>
                <a href="index.php?page=backdate&action=delete&id=<?= $bf['id'] ?>" class="btn btn-sm btn-outline-danger"
                   data-confirm="Delete this backdate data?" data-confirm-title="Delete this backdate data?"
                   data-confirm-ok="Delete">
                    <i class="bi bi-trash"></i> Delete
                </a>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

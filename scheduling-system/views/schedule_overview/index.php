<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2><i class="bi bi-calendar2-week"></i> Schedule Overview</h2>
        <p class="text-muted mb-0">Daftar schedule yang sudah dibuat melalui Run Automation.</p>
    </div>
    <div>
        <a href="index.php?page=schedule" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Generate Schedule
        </a>
    </div>
</div>

<?php if (empty($weeks)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-calendar2-week" style="font-size:3rem;opacity:0.3;"></i>
            <p class="mt-3 text-muted">Belum ada schedule yang dibuat.</p>
            <a href="index.php?page=schedule" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Generate Schedule
            </a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($weeks as $week): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-calendar-week"></i>
                    <strong>Week <?= date('d M Y', strtotime($week['week_start_date'])) ?> — <?= date('d M Y', strtotime($week['week_start_date'] . ' +6 days')) ?></strong>
                    <span class="badge bg-<?= $week['status'] === 'Final' ? 'success' : 'secondary' ?> ms-2"><?= $week['status'] ?></span>
                </span>
                <span class="badge bg-light text-dark"><?= $week['detail_count'] ?> entries</span>
            </div>
            <div class="card-body">
                <a href="index.php?page=schedule_overview&action=view&id=<?= $week['id'] ?>" class="btn btn-sm btn-outline-info">
                    <i class="bi bi-eye"></i> View
                </a>
                <a href="index.php?page=schedule_overview&action=delete&id=<?= $week['id'] ?>" class="btn btn-sm btn-outline-danger"
                   data-confirm="Hapus schedule minggu ini?" data-confirm-title="Hapus schedule minggu ini?"
                   data-confirm-ok="Ya, hapus">
                    <i class="bi bi-trash"></i> Delete
                </a>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

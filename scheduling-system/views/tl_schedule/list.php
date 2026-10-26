<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-person-badge"></i> Team Leader Schedule</h2>
    </div>
    <div class="col-auto">
        <a href="index.php?page=tl_schedule&action=input" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Bulk Input
        </a>
    </div>
</div>

<?php if (empty($weeks)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-person-badge" style="font-size:3rem;opacity:0.3;"></i>
            <p class="mt-3 text-muted">Belum ada data TL Schedule. Silakan tambah melalui <a href="index.php?page=tl_schedule&action=input">Bulk Input</a>.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($weeks as $week): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-calendar-week"></i> Week <?= date('d M Y', strtotime($week['week_start'])) ?> — <?= date('d M Y', strtotime($week['week_start'] . ' +6 days')) ?></strong>
                <span class="badge bg-secondary"><?= $week['count'] ?> entries</span>
            </div>
            <div class="card-body">
                <a href="index.php?page=tl_schedule&action=overview&week=<?= urlencode($week['week_start']) ?>" class="btn btn-sm btn-outline-info">
                    <i class="bi bi-eye"></i> Overview
                </a>
                <a href="index.php?page=tl_schedule&action=deleteWeek&week=<?= urlencode($week['week_start']) ?>" class="btn btn-sm btn-outline-danger"
                   data-confirm="Hapus semua data TL Schedule untuk minggu ini?"
                   data-confirm-title="Hapus data TL Schedule?"
                   data-confirm-ok="Ya, hapus">
                    <i class="bi bi-trash"></i> Delete
                </a>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

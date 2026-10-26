<div class="portal-hero text-center">
    <?php
        $jamNow   = (int) (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('G');
        $sapaan   = $jamNow < 11 ? 'Selamat pagi' : ($jamNow < 15 ? 'Selamat siang' : ($jamNow < 19 ? 'Selamat sore' : 'Selamat malam'));
    ?>
    <h1 class="h3 mb-1"><?= htmlspecialchars($sapaan) ?><?= !empty($userName) ? ', ' . htmlspecialchars($userName) : '' ?></h1>
    <p class="text-muted mb-0">Silakan pilih aplikasi yang ingin dibuka.</p>
</div>

<?php if (!empty($links)): ?>
    <div class="portal-shortcuts">
        <?php foreach ($links as $link): ?>
            <a class="portal-shortcut" href="<?= htmlspecialchars($link['url']) ?>">
                <i class="bi <?= htmlspecialchars($link['icon']) ?>"></i> <?= htmlspecialchars($link['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-4 justify-content-center portal-grid">
    <?php foreach ($apps as $app): ?>
        <div class="col-md-6 col-xl-5">
            <a href="<?= htmlspecialchars($app['url']) ?>" class="portal-card text-decoration-none">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-start gap-3">
                        <div class="portal-icon">
                            <i class="bi <?= htmlspecialchars($app['icon']) ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h2 class="h5 mb-0"><?= htmlspecialchars($app['title']) ?></h2>
                                <?php if (!empty($app['badge'])): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($app['badge']) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted small mb-0"><?= htmlspecialchars($app['description']) ?></p>
                        </div>
                        <i class="bi bi-arrow-right-circle portal-arrow"></i>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

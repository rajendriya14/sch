<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

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

<div class="mx-auto" style="max-width: 1080px;">
    <div class="row g-4 portal-grid">
        <?php foreach ($apps as $app): ?>
            <div class="col-12 col-md-6">
                <a href="<?= htmlspecialchars($app['url']) ?>" class="portal-card text-decoration-none">
                    <div class="card h-100">
                        <?php if (!empty($app['logo_only'])): ?>
                            <div class="card-body d-flex align-items-center justify-content-between p-3" style="min-height: 98px;">
                                <div style="width: 28px; visibility: hidden;" class="d-none d-sm-block" aria-hidden="true"></div>
                                <div class="d-flex align-items-center justify-content-center flex-grow-1">
                                    <svg viewBox="0 0 108 36" width="190" height="62" xmlns="http://www.w3.org/2000/svg" style="display: block; max-width: 100%; height: auto; max-height: 58px; transition: transform 0.2s ease;">
                                        <defs>
                                            <pattern id="portalPencilHatchPattern" width="4" height="4" patternTransform="rotate(45 0 0)" patternUnits="userSpaceOnUse">
                                                <line x1="0" y1="0" x2="0" y2="4" stroke="#1e293b" stroke-width="1.2" opacity="0.82" />
                                            </pattern>
                                        </defs>
                                        <text x="4" y="27" font-family="'Space Grotesk', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="28" font-weight="700" letter-spacing="-0.5px" fill="url(#portalPencilHatchPattern)" stroke="#0f172a" stroke-width="1.15" stroke-linejoin="round">rekha</text>
                                    </svg>
                                </div>
                                <i class="bi bi-arrow-right-circle portal-arrow"></i>
                            </div>
                        <?php else: ?>
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
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

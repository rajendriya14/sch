<?php
/**
 * BAK (Berita Acara Kerja) — wizard 3 langkah.
 *
 * Komponen React hidup di assets/js/BAKStepper.tsx dan di-mount ke #bak-mount.
 * Server ini tidak punya toolchain Node, jadi berkas .tsx dikompilasi di browser
 * memakai Babel Standalone (lihat assets/js/bak-loader.js).
 *
 * Ubah base URL API lewat window.BAK_CONFIG.apiBaseUrl di bawah.
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-receipt"></i> BAK - Berita Acara Kerja</h5>
        <small class="text-muted">
            Max Potential Earnings &rarr; Accrual Input &rarr; Actual Input (Finance Verification)
        </small>
    </div>
</div>

<div id="bak-mount">
    <div class="text-center text-muted py-5">
        <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
        Memuat komponen BAK&hellip;
    </div>
</div>

<script>
    window.BAK_CONFIG = {
        // Endpoint API BAK. Ganti bila backend memakai pola URL lain
        // (mis. 'index.php?page=bak&action=api').
        apiBaseUrl: '/api/bak',
        mountId: 'bak-mount',
        // ?v=filemtime() mencegah browser memakai salinan .tsx yang basi
        // setelah deploy (konvensi sama seperti style.css di header.php).
        sourceUrl: 'scheduling-system/public/assets/js/BAKStepper.tsx?v=<?= @filemtime(__DIR__ . '/../../public/assets/js/BAKStepper.tsx') ?: '1' ?>'
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/react@18.3.1/umd/react.production.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/react-dom@18.3.1/umd/react-dom.production.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@babel/standalone@7.26.4/babel.min.js"></script>
<script src="scheduling-system/public/assets/js/bak-loader.js?v=<?= @filemtime(__DIR__ . '/../../public/assets/js/bak-loader.js') ?: '1' ?>"></script>

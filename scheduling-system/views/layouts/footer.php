            </div><!-- /.container-fluid -->
        </main><!-- /mainContent -->
    </div><!-- /wrapper -->

    <footer class="footer py-3 bg-light border-top">
        <div class="container-fluid px-4">
            <p class="text-muted text-center mb-0">
                &copy; <?= date('Y') ?> Workforce Automation Scheduling System &mdash; MVP Phase
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="scheduling-system/public/assets/js/dialog.js?v=<?= @filemtime(__DIR__ . '/../../public/assets/js/dialog.js') ?: '1' ?>"></script>
    <script src="scheduling-system/public/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../../public/assets/js/app.js') ?: '1' ?>"></script>
    <?php if (($pageName ?? '') === 'ffr'): ?>
        <script src="scheduling-system/public/assets/js/ffr.js?v=<?= @filemtime(__DIR__ . '/../../public/assets/js/ffr.js') ?: '1' ?>"></script>
    <?php endif; ?>

<script>
/**
 * Jam realtime di header (WIB / UTC+7) — update tiap detik tanpa reload
 */
(function () {
    var clock = document.getElementById('headerClock');
    if (!clock) return;

    function tick() {
        var now = new Date();
        // Konversi ke WIB (UTC+7) agar konsisten walau jam OS/PC beda zona
        var wib = new Date(now.getTime() + (now.getTimezoneOffset() * 60000) + (7 * 3600000));
        var hh = String(wib.getHours()).padStart(2, '0');
        var mm = String(wib.getMinutes()).padStart(2, '0');
        clock.textContent = hh + ':' + mm;
    }

    tick();
    setInterval(tick, 1000);
})();
</script>

<script>
/**
 * Auto-expand sidebar submenus based on current page
 * AND persist state across navigations using sessionStorage
 */
document.addEventListener('DOMContentLoaded', function () {
    // Get the current page name from the body or URL
    var currentPage = '<?= $pageName ?? '' ?>';
    var currentSubPage = '<?= $subPage ?? '' ?>';

    // Default: expand Content Moderator if on content/backdate pages
    var expandContentMod = ['content', 'backdate', 'shifts', 'staff', 'schedule', 'forecast', 'leave', 'tl_schedule', 'dashboard'].indexOf(currentPage) >= 0;

    // Expand Configuration if on shifts, staff, or backdate
    var expandConfig = ['shifts', 'staff', 'backdate'].indexOf(currentPage) >= 0;

    // Expand Scheduling if on schedule
    var expandSched = currentPage === 'schedule';

    // Helper to show collapse
    function showCollapse(id) {
        var el = document.getElementById(id);
        if (el) {
            el.classList.add('show');
            var trigger = document.querySelector('[href="#' + id + '"]');
            if (trigger) trigger.setAttribute('aria-expanded', 'true');
        }
    }

    // Check sessionStorage for saved states
    var savedContentMod = sessionStorage.getItem('sb_contentModMenu');
    var savedConfig = sessionStorage.getItem('sb_configSubMenu');
    var savedSched = sessionStorage.getItem('sb_schedSubMenu');

    // Use saved state if exists, otherwise use page-based logic
    if (savedContentMod === 'true' || (savedContentMod === null && expandContentMod)) showCollapse('contentModMenu');
    if (savedConfig === 'true' || (savedConfig === null && expandConfig)) showCollapse('configSubMenu');
    if (savedSched === 'true' || (savedSched === null && expandSched)) showCollapse('schedSubMenu');

    // Save state when toggles are clicked
    document.querySelectorAll('.sidebar-nav [data-bs-toggle="collapse"]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var targetId = this.getAttribute('href').replace('#', '');
            var target = document.getElementById(targetId);
            if (target) {
                var isOpen = target.classList.contains('show');
                sessionStorage.setItem('sb_' + targetId, isOpen ? 'false' : 'true');
            }
        });
    });
});
</script>

</body>
</html>

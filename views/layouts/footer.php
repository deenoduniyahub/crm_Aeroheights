    </div><!-- End Master Layout Body Wrapper -->

    <!-- Dynamic Modal Container for CRUD Actions -->
    <div id="dynamicModalContainer"></div>

    <!-- Application JavaScript Bundles -->
    <?php
    // Cache-bust on each script's own last-modified time so a deployed edit is picked up
    // immediately instead of waiting on a stale browser-cached copy (which throws
    // "X is not defined" for any function added after the browser cached the old file).
    $assetVersion = static function (string $relativePath): string {
        $fullPath = __DIR__ . '/../../' . $relativePath;
        $mtime = @filemtime($fullPath);
        return $relativePath . ($mtime ? ('?v=' . $mtime) : '');
    };
    ?>
    <script src="<?= $assetVersion('assets/js/app.js') ?>"></script>
    <script src="<?= $assetVersion('assets/js/ledgers.js') ?>"></script>
    <script src="<?= $assetVersion('assets/js/voucher.js') ?>"></script>
    <script src="<?= $assetVersion('assets/js/hotel_booking.js') ?>"></script>

    <!-- Global Layout Helper Script -->
    <script>
        function updateGlobalClock() {
            const clockEl = document.getElementById('globalClockDisplay');
            if (clockEl) {
                const now = new Date();
                clockEl.textContent = now.toLocaleDateString('en-GB') + ' ' + now.toLocaleTimeString();
            }
        }
        setInterval(updateGlobalClock, 1000);
        updateGlobalClock();

        function closeActiveModal() {
            const modal = document.getElementById('dynamicModalContainer');
            if (modal) modal.innerHTML = '';
        }

        // Mobile off-canvas navigation drawer
        function toggleMobileSidebar(open) {
            const sidebar = document.getElementById('mobileSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            const toggleBtn = document.querySelector('[aria-controls="mobileSidebar"]');
            if (!sidebar || !backdrop) return;
            if (open) {
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                backdrop.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
            } else {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
                backdrop.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
            }
        }

        // Close the drawer when a nav link inside it is tapped (before page navigates away)
        document.getElementById('mobileSidebar')?.addEventListener('click', function (e) {
            if (e.target.closest('a')) toggleMobileSidebar(false);
        });

        // Close on Escape, and reset state if the viewport grows past the mobile breakpoint
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') toggleMobileSidebar(false);
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 768) toggleMobileSidebar(false);
        });
    </script>
</body>
</html>


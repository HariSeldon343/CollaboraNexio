<?php
/**
 * Shared layout end include.
 *
 * Usage (at the end of page body):
 *   require __DIR__ . '/includes/layout_end.php';
 */
declare(strict_types=1);
?>
    </div>
</div>

<?php
  // Ensure core app bootstrap is present on every page using the shared layout
  $assetBase = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
  $assetPrefix = $assetBase !== '' ? ($assetBase . '/') : '';
  // CNX UI redesign 2026-05 — cache-bust app.js so initThemeToggle() ships to every browser
  $cnxAppJsV = (string)((@filemtime(__DIR__ . '/../assets/js/app.js') ?: time()) . '-' . (@filesize(__DIR__ . '/../assets/js/app.js') ?: 0));
?>
<script src="<?php echo htmlspecialchars($assetPrefix . 'assets/js/app.js?v=' . $cnxAppJsV); ?>"></script>
</body>
</html>


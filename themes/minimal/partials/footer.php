<?php

use App\Content\Model\Navigation;
use App\Render\NavigationRenderer;
use App\Build\AssetFingerprintManifest;
use App\Build\Asset;

/**
 * @var ?Navigation $nav
 * @var string $rootPath
 * @var AssetFingerprintManifest|null $assetManifest
 */
?>
<?php if ($nav !== null && $nav->menu('footer') !== []): ?>
<footer class="site-footer">
    <div class="container">
        <?= NavigationRenderer::render($nav, 'footer') ?>
    </div>
</footer>
<?php endif; ?>
<script src="<?= htmlspecialchars(Asset::url('assets/theme/dark-mode.js', $rootPath, $assetManifest)) ?>"></script>

<?php

use App\Content\Model\Navigation;
use App\Render\NavigationRenderer;

/**
 * @var ?Navigation $nav
 */
?>
<?php if ($nav !== null && $nav->menu('footer') !== []): ?>
<footer class="site-footer">
    <div class="container">
        <?= NavigationRenderer::render($nav, 'footer') ?>
    </div>
</footer>
<?php endif; ?>
<script src="<?= $rootPath ?>assets/theme/dark-mode.js"></script>

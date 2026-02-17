<?php

use App\Render\NavigationRenderer;

/**
 * @var string $siteTitle
 * @var ?\App\Content\Model\Navigation $nav
 */
?>
<header class="site-header">
    <div class="container">
        <a class="site-name" href="/"><?= htmlspecialchars($siteTitle) ?></a>
<?php if ($nav !== null && $nav->menu('main') !== []): ?>
        <?= NavigationRenderer::render($nav, 'main') ?>
<?php endif; ?>
        <button class="theme-toggle" type="button" aria-label="Toggle dark mode"></button>
    </div>
</header>

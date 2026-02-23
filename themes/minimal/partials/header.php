<?php

use App\Content\Model\Navigation;
use App\Render\NavigationRenderer;

/**
 * @var string $siteTitle
 * @var ?Navigation $nav
 */
?>
<header class="site-header">
    <div class="container">
        <a class="site-name" href="<?= $rootPath ?>"><?= htmlspecialchars($siteTitle) ?></a>
<?php if ($nav !== null && $nav->menu('main') !== []): ?>
        <?= NavigationRenderer::render($nav, 'main', $rootPath) ?>
<?php endif; ?>
        <button class="theme-toggle" type="button" aria-label="Toggle dark mode"></button>
    </div>
</header>

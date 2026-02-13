<?php

declare(strict_types=1);

use App\Render\NavigationRenderer;

/**
 * @var string $siteTitle
 * @var string $taxonomyName
 * @var list<string> $terms
 * @var ?\App\Content\Model\Navigation $nav
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(ucfirst($taxonomyName)) ?> — <?= htmlspecialchars($siteTitle) ?></title>
</head>
<body>
<header>
<?php if ($nav !== null && $nav->menu('main') !== []): ?>
    <?= NavigationRenderer::render($nav, 'main') ?>
<?php else: ?>
    <nav><a href="/"><?= htmlspecialchars($siteTitle) ?></a></nav>
<?php endif; ?>
</header>
<main>
    <h1><?= htmlspecialchars(ucfirst($taxonomyName)) ?></h1>
    <ul>
<?php foreach ($terms as $term): ?>
        <li><a href="/<?= htmlspecialchars($taxonomyName) ?>/<?= htmlspecialchars($term) ?>/"><?= htmlspecialchars($term) ?></a></li>
<?php endforeach; ?>
    </ul>
</main>
<?php if ($nav !== null && $nav->menu('footer') !== []): ?>
<footer>
    <?= NavigationRenderer::render($nav, 'footer') ?>
</footer>
<?php endif; ?>
</body>
</html>

<?php

declare(strict_types=1);

use App\Render\NavigationRenderer;

/**
 * @var string $siteTitle
 * @var string $taxonomyName
 * @var string $term
 * @var list<array{title: string, url: string, date: string}> $entries
 * @var ?\App\Content\Model\Navigation $nav
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($term) ?> — <?= htmlspecialchars(ucfirst($taxonomyName)) ?> — <?= htmlspecialchars($siteTitle) ?></title>
    <link rel="stylesheet" href="/assets/theme/style.css">
</head>
<body>
<header class="site-header">
    <div class="container">
        <a class="site-name" href="/"><?= htmlspecialchars($siteTitle) ?></a>
<?php if ($nav !== null && $nav->menu('main') !== []): ?>
        <?= NavigationRenderer::render($nav, 'main') ?>
<?php endif; ?>
        <button class="theme-toggle" type="button" aria-label="Toggle dark mode"></button>
    </div>
</header>
<main>
    <div class="container">
        <h1><?= htmlspecialchars(ucfirst($taxonomyName)) ?>: <?= htmlspecialchars($term) ?></h1>
        <ul class="entry-list">
<?php foreach ($entries as $entry): ?>
            <li>
                <a href="<?= htmlspecialchars($entry['url']) ?>"><?= htmlspecialchars($entry['title']) ?></a>
<?php if ($entry['date'] !== ''): ?>
                <time datetime="<?= htmlspecialchars($entry['date']) ?>"><?= htmlspecialchars($entry['date']) ?></time>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ul>
    </div>
</main>
<?php if ($nav !== null && $nav->menu('footer') !== []): ?>
<footer class="site-footer">
    <div class="container">
        <?= NavigationRenderer::render($nav, 'footer') ?>
    </div>
</footer>
<?php endif; ?>
<script src="/assets/theme/dark-mode.js"></script>
</body>
</html>

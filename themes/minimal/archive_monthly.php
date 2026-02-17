<?php

declare(strict_types=1);

use App\Render\NavigationRenderer;

/**
 * @var string $siteTitle
 * @var string $collectionName
 * @var string $collectionTitle
 * @var string $year
 * @var string $month
 * @var string $monthName
 * @var list<array{title: string, url: string, date: string}> $entries
 * @var ?\App\Content\Model\Navigation $nav
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($collectionTitle) ?>: <?= htmlspecialchars($monthName) ?> <?= $year ?> — <?= htmlspecialchars($siteTitle) ?></title>
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
        <h1><?= htmlspecialchars($collectionTitle) ?>: <?= htmlspecialchars($monthName) ?> <?= $year ?></h1>
        <p class="back-link"><a href="/<?= htmlspecialchars($collectionName) ?>/<?= $year ?>/">&larr; All of <?= $year ?></a></p>
        <ul class="entry-list">
<?php foreach ($entries as $entry): ?>
            <li>
                <a href="<?= htmlspecialchars($entry['url']) ?>"><?= htmlspecialchars($entry['title']) ?></a>
<?php if ($entry['date'] !== ''): ?>
                <time><?= htmlspecialchars($entry['date']) ?></time>
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

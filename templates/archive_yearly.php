<?php

declare(strict_types=1);

use App\Render\NavigationRenderer;

/**
 * @var string $siteTitle
 * @var string $collectionName
 * @var string $collectionTitle
 * @var string $year
 * @var list<string> $months
 * @var list<array{title: string, url: string, date: string}> $entries
 * @var ?\App\Content\Model\Navigation $nav
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($collectionTitle) ?>: <?= $year ?> — <?= htmlspecialchars($siteTitle) ?></title>
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
    <h1><?= htmlspecialchars($collectionTitle) ?>: <?= $year ?></h1>
    <nav class="archive-months">
        <ul>
<?php foreach ($months as $m): ?>
            <li><a href="/<?= htmlspecialchars($collectionName) ?>/<?= $year ?>/<?= $m ?>/"><?= date('F', mktime(0, 0, 0, (int) $m, 1)) ?></a></li>
<?php endforeach; ?>
        </ul>
    </nav>
    <ul>
<?php foreach ($entries as $entry): ?>
        <li>
            <a href="<?= htmlspecialchars($entry['url']) ?>"><?= htmlspecialchars($entry['title']) ?></a>
<?php if ($entry['date'] !== ''): ?>
            <time><?= htmlspecialchars($entry['date']) ?></time>
<?php endif; ?>
        </li>
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

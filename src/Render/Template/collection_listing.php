<?php

declare(strict_types=1);

use App\Render\NavigationRenderer;

/**
 * @var string $siteTitle
 * @var string $collectionTitle
 * @var list<array{title: string, url: string, date: string, summary: string}> $entries
 * @var array{currentPage: int, totalPages: int, previousUrl: string, nextUrl: string} $pagination
 * @var ?\App\Content\Model\Navigation $nav
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($collectionTitle) ?><?= $pagination['currentPage'] > 1 ? ' — Page ' . $pagination['currentPage'] : '' ?> — <?= htmlspecialchars($siteTitle) ?></title>
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
    <h1><?= htmlspecialchars($collectionTitle) ?></h1>
<?php if ($entries === []): ?>
    <p>No entries.</p>
<?php else: ?>
    <ul>
<?php foreach ($entries as $entry): ?>
        <li>
            <a href="<?= htmlspecialchars($entry['url']) ?>"><?= htmlspecialchars($entry['title']) ?></a>
<?php if ($entry['date'] !== ''): ?>
            <time><?= htmlspecialchars($entry['date']) ?></time>
<?php endif; ?>
<?php if ($entry['summary'] !== ''): ?>
            <p><?= htmlspecialchars($entry['summary']) ?></p>
<?php endif; ?>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php if ($pagination['totalPages'] > 1): ?>
    <nav class="pagination">
<?php if ($pagination['previousUrl'] !== ''): ?>
        <a href="<?= htmlspecialchars($pagination['previousUrl']) ?>" rel="prev">← Previous</a>
<?php endif; ?>
        <span>Page <?= $pagination['currentPage'] ?> of <?= $pagination['totalPages'] ?></span>
<?php if ($pagination['nextUrl'] !== ''): ?>
        <a href="<?= htmlspecialchars($pagination['nextUrl']) ?>" rel="next">Next →</a>
<?php endif; ?>
    </nav>
<?php endif; ?>
</main>
<?php if ($nav !== null && $nav->menu('footer') !== []): ?>
<footer>
    <?= NavigationRenderer::render($nav, 'footer') ?>
</footer>
<?php endif; ?>
</body>
</html>

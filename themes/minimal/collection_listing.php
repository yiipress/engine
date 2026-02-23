<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $collectionTitle
 * @var list<array{title: string, url: string, date: string, summary: string}> $entries
 * @var array{currentPage: int, totalPages: int, previousUrl: string, nextUrl: string} $pagination
 * @var ?Navigation $nav
 * @var Closure(string, array): string $partial
 * @var string $rootPath
 */

use App\Content\Model\Navigation;

$pageTitle = $collectionTitle . ($pagination['currentPage'] > 1 ? ' — Page ' . $pagination['currentPage'] : '') . ' — ' . $siteTitle;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= $partial('head', ['title' => $pageTitle, 'rootPath' => $rootPath]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath]) ?>
<main>
    <div class="container">
        <h1><?= htmlspecialchars($collectionTitle) ?></h1>
<?php if ($entries === []): ?>
        <p>No entries.</p>
<?php else: ?>
        <ul class="entry-list">
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
            <a href="<?= htmlspecialchars($pagination['previousUrl']) ?>" rel="prev">&larr; Previous</a>
<?php endif; ?>
            <span>Page <?= $pagination['currentPage'] ?> of <?= $pagination['totalPages'] ?></span>
<?php if ($pagination['nextUrl'] !== ''): ?>
            <a href="<?= htmlspecialchars($pagination['nextUrl']) ?>" rel="next">Next &rarr;</a>
<?php endif; ?>
        </nav>
<?php endif; ?>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath]) ?>
</body>
</html>

<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $collectionName
 * @var string $collectionTitle
 * @var string $year
 * @var string $month
 * @var string $monthName
 * @var list<array{title: string, url: string, date: string}> $entries
 * @var ?\App\Content\Model\Navigation $nav
 * @var Closure(string, array): string $partial
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= $partial('head', ['title' => $collectionTitle . ': ' . $monthName . ' ' . $year . ' — ' . $siteTitle]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav]) ?>
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
<?= $partial('footer', ['nav' => $nav]) ?>
</body>
</html>

<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $collectionName
 * @var string $collectionTitle
 * @var string $year
 * @var list<string> $months
 * @var list<array{title: string, url: string, date: string}> $entries
 * @var ?Navigation $nav
 * @var Closure(string, array): string $partial
 */

use App\Content\Model\Navigation;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= $partial('head', ['title' => $collectionTitle . ': ' . $year . ' — ' . $siteTitle]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav]) ?>
<main>
    <div class="container">
        <h1><?= htmlspecialchars($collectionTitle) ?>: <?= $year ?></h1>
        <nav class="archive-months">
            <ul>
<?php foreach ($months as $m): ?>
                <li><a href="/<?= htmlspecialchars($collectionName) ?>/<?= $year ?>/<?= $m ?>/"><?= date('F', mktime(0, 0, 0, (int) $m, 1)) ?></a></li>
<?php endforeach; ?>
            </ul>
        </nav>
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

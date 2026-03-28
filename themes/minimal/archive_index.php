<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $collectionName
 * @var string $collectionTitle
 * @var list<string> $years
 * @var ?Navigation $nav
 * @var Closure(string, array): string $partial
 * @var string $rootPath
 */

use App\Content\Model\Navigation;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= $partial('head', ['title' => $collectionTitle . ' Archive — ' . $siteTitle, 'rootPath' => $rootPath, 'metaTags' => $metaTags, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10]) ?>
<main>
    <div class="container">
        <h1><?= htmlspecialchars($collectionTitle) ?> Archive</h1>
        <ul class="archive-years">
<?php foreach ($years as $year): ?>
            <li><a href="<?= $rootPath . htmlspecialchars($collectionName) . '/' . htmlspecialchars((string)$year) ?>/"><?= htmlspecialchars((string)$year) ?></a></li>
<?php endforeach; ?>
        </ul>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath]) ?>
</body>
</html>

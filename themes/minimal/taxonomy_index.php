<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $taxonomyName
 * @var list<string> $terms
 * @var ?\App\Content\Model\Navigation $nav
 * @var Closure(string, array): string $partial
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= $partial('head', ['title' => ucfirst($taxonomyName) . ' — ' . $siteTitle]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav]) ?>
<main>
    <div class="container">
        <h1><?= htmlspecialchars(ucfirst($taxonomyName)) ?></h1>
        <ul class="term-list">
<?php foreach ($terms as $term): ?>
            <li><a href="/<?= htmlspecialchars($taxonomyName) ?>/<?= htmlspecialchars($term) ?>/"><?= htmlspecialchars($term) ?></a></li>
<?php endforeach; ?>
        </ul>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav]) ?>
</body>
</html>

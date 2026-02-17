<?php

declare(strict_types=1);

use App\Render\NavigationRenderer;

/**
 * @var string $siteTitle
 * @var list<array{title: string, url: string, avatar: string}> $authorList
 * @var ?\App\Content\Model\Navigation $nav
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authors — <?= htmlspecialchars($siteTitle) ?></title>
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
    <h1>Authors</h1>
    <ul>
<?php foreach ($authorList as $author): ?>
        <li>
<?php if ($author['avatar'] !== ''): ?>
            <img src="<?= htmlspecialchars($author['avatar']) ?>" alt="<?= htmlspecialchars($author['title']) ?>">
<?php endif; ?>
            <a href="<?= htmlspecialchars($author['url']) ?>"><?= htmlspecialchars($author['title']) ?></a>
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

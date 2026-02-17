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
        <h1>Authors</h1>
        <ul class="author-grid">
<?php foreach ($authorList as $author): ?>
            <li>
<?php if ($author['avatar'] !== ''): ?>
                <img src="<?= htmlspecialchars($author['avatar']) ?>" alt="<?= htmlspecialchars($author['title']) ?>">
<?php endif; ?>
                <a href="<?= htmlspecialchars($author['url']) ?>"><?= htmlspecialchars($author['title']) ?></a>
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

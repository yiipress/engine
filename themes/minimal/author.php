<?php

declare(strict_types=1);

use App\Render\NavigationRenderer;

/**
 * @var string $siteTitle
 * @var string $authorTitle
 * @var string $authorEmail
 * @var string $authorUrl
 * @var string $authorAvatar
 * @var string $authorBio
 * @var list<array{title: string, url: string, date: string}> $entries
 * @var ?\App\Content\Model\Navigation $nav
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($authorTitle) ?> — <?= htmlspecialchars($siteTitle) ?></title>
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
        <div class="author-profile">
<?php if ($authorAvatar !== ''): ?>
            <img src="<?= htmlspecialchars($authorAvatar) ?>" alt="<?= htmlspecialchars($authorTitle) ?>">
<?php endif; ?>
            <div class="author-info">
                <h1><?= htmlspecialchars($authorTitle) ?></h1>
<?php if ($authorEmail !== '' || $authorUrl !== ''): ?>
                <div class="author-links">
<?php if ($authorEmail !== ''): ?>
                    <a href="mailto:<?= htmlspecialchars($authorEmail) ?>"><?= htmlspecialchars($authorEmail) ?></a>
<?php endif; ?>
<?php if ($authorUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($authorUrl) ?>"><?= htmlspecialchars($authorUrl) ?></a>
<?php endif; ?>
                </div>
<?php endif; ?>
<?php if ($authorBio !== ''): ?>
                <div class="bio content"><?= $authorBio ?></div>
<?php endif; ?>
            </div>
        </div>
<?php if ($entries !== []): ?>
        <h2>Posts</h2>
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
<?php endif; ?>
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

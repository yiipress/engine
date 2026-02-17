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
    <article class="author">
<?php if ($authorAvatar !== ''): ?>
        <img src="<?= htmlspecialchars($authorAvatar) ?>" alt="<?= htmlspecialchars($authorTitle) ?>">
<?php endif; ?>
        <h1><?= htmlspecialchars($authorTitle) ?></h1>
<?php if ($authorEmail !== ''): ?>
        <p><a href="mailto:<?= htmlspecialchars($authorEmail) ?>"><?= htmlspecialchars($authorEmail) ?></a></p>
<?php endif; ?>
<?php if ($authorUrl !== ''): ?>
        <p><a href="<?= htmlspecialchars($authorUrl) ?>"><?= htmlspecialchars($authorUrl) ?></a></p>
<?php endif; ?>
<?php if ($authorBio !== ''): ?>
        <div class="bio"><?= $authorBio ?></div>
<?php endif; ?>
    </article>
<?php if ($entries !== []): ?>
    <h2>Posts</h2>
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
<?php endif; ?>
</main>
<?php if ($nav !== null && $nav->menu('footer') !== []): ?>
<footer>
    <?= NavigationRenderer::render($nav, 'footer') ?>
</footer>
<?php endif; ?>
</body>
</html>

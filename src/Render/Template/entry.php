<?php

declare(strict_types=1);

use App\Render\NavigationRenderer;

/**
 * @var string $siteTitle
 * @var string $entryTitle
 * @var string $content
 * @var string $date
 * @var string $author
 * @var string $collection
 * @var ?\App\Content\Model\Navigation $nav
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($entryTitle) ?> — <?= htmlspecialchars($siteTitle) ?></title>
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
    <article>
        <h1><?= htmlspecialchars($entryTitle) ?></h1>
<?php if ($date !== ''): ?>
        <time datetime="<?= htmlspecialchars($date) ?>"><?= htmlspecialchars($date) ?></time>
<?php endif; ?>
<?php if ($author !== ''): ?>
        <span class="author"><?= htmlspecialchars($author) ?></span>
<?php endif; ?>
        <div class="content">
            <?= $content ?>
        </div>
    </article>
</main>
<?php if ($nav !== null && $nav->menu('footer') !== []): ?>
<footer>
    <?= NavigationRenderer::render($nav, 'footer') ?>
</footer>
<?php endif; ?>
</body>
</html>

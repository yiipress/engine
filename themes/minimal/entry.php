<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $entryTitle
 * @var string $content
 * @var string $date
 * @var string $author
 * @var string $collection
 * @var ?Navigation $nav
 * @var Closure(string, array): string $partial
 */

use App\Content\Model\Navigation;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= $partial('head', ['title' => $entryTitle . ' — ' . $siteTitle]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav]) ?>
<main>
    <div class="container">
        <article>
            <h1><?= htmlspecialchars($entryTitle) ?></h1>
<?php if ($date !== '' || $author !== ''): ?>
            <div class="entry-meta">
<?php if ($date !== ''): ?>
                <time datetime="<?= htmlspecialchars($date) ?>"><?= htmlspecialchars($date) ?></time>
<?php endif; ?>
<?php if ($author !== ''): ?>
                <span class="author"><?= htmlspecialchars($author) ?></span>
<?php endif; ?>
            </div>
<?php endif; ?>
            <div class="content">
                <?= $content ?>
            </div>
        </article>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav]) ?>
</body>
</html>

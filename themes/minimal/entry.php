<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $entryTitle
 * @var string $content
 * @var string $date
 * @var string $dateISO
 * @var bool $draft
 * @var string $author
 * @var list<string> $tags
 * @var list<string> $categories
 * @var string $collection
 * @var string $headAssets
 * @var ?Navigation $nav
 * @var Closure(string, array): string $partial
 * @var string $rootPath
 */

use App\Content\Model\Navigation;

?>
<!DOCTYPE html>
<html lang="en">
<head>

<?= $partial('head', ['title' => $entryTitle . ' — ' . $siteTitle, 'rootPath' => $rootPath, 'headAssets' => $headAssets ?? '', 'metaTags' => $metaTags]) ?>

</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath]) ?>
<main>
    <div class="container">
        <article>
            <h1><?= htmlspecialchars($entryTitle) ?></h1>
<?php if ($draft || ($dateISO !== '' && $dateISO > date('Y-m-d')) || $date !== '' || $author !== ''): ?>
            <div class="entry-meta">
<?php if ($draft): ?>
                <span class="badge badge-draft">Draft</span>
<?php endif; ?>
<?php if ($dateISO !== '' && $dateISO > date('Y-m-d')): ?>
                <span class="badge badge-future">Scheduled: <?= htmlspecialchars($date) ?></span>
<?php elseif ($date !== ''): ?>
                <time datetime="<?= htmlspecialchars($dateISO) ?>"><?= htmlspecialchars($date) ?></time>
<?php endif; ?>
<?php if ($author !== ''): ?>
                <span class="author"><?= htmlspecialchars($author) ?></span>
<?php endif; ?>
            </div>
<?php endif; ?>
            <div class="content">
                <?= $content ?>
            </div>
<?php if ($tags !== [] || $categories !== []): ?>
            <footer class="entry-footer">
<?php if ($tags !== []): ?>
                <div class="entry-tags">
<?php foreach ($tags as $tag): ?>
                    <a href="<?= $rootPath ?>tags/<?= htmlspecialchars($tag) ?>/" class="tag"><?= htmlspecialchars($tag) ?></a>
<?php endforeach; ?>
                </div>
<?php endif; ?>
<?php if ($categories !== []): ?>
                <div class="entry-categories">
<?php foreach ($categories as $category): ?>
                    <a href="<?= $rootPath ?>categories/<?= htmlspecialchars($category) ?>/" class="category"><?= htmlspecialchars($category) ?></a>
<?php endforeach; ?>
                </div>
<?php endif; ?>
            </footer>
<?php endif; ?>
        </article>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath]) ?>
</body>
</html>

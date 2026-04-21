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
 * @var list<array{id: string, text: string, level: int}> $toc
 * @var list<App\Content\Model\RelatedEntry> $related
 * @var list<App\Content\Model\Translation> $translations
 * @var string $language
 * @var ?Navigation $nav
 * @var Closure(string, array): string $partial
 * @var string $rootPath
 * @var App\Build\MetaTags $metaTags
 * @var bool $search
 * @var int $searchResults
 * @var string $uiLanguage
 * @var list<string> $uiLanguages
 * @var array<string, array<string, string>> $uiCatalogs
 * @var App\I18n\UiText $ui
 * @var Closure(string, array): string $t
 */

use App\Content\Model\Navigation;
$language ??= 'en';
$uiLanguage ??= 'en';
$translations ??= [];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language) ?>">
<head>

<?= $partial('head', ['title' => $entryTitle . ' — ' . $siteTitle, 'rootPath' => $rootPath, 'headAssets' => $headAssets ?? '', 'metaTags' => $metaTags, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage], 'uiCatalogs' => $uiCatalogs ?? [$uiLanguage => []]]) ?>

</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage]]) ?>
<main>
    <div class="container">
<?php $hasSidebar = $toc !== []; ?>
<?php if ($hasSidebar): ?>
        <div class="article-with-sidebar">
<?php endif; ?>
<?php if ($hasSidebar): ?>
            <aside class="toc-sidebar" aria-label="<?= htmlspecialchars($t('table_of_contents')) ?>" data-ui-attr-aria-label="table_of_contents">
                <nav>
                    <ol>
<?php foreach ($toc as $item): ?>
                        <li class="toc-level-<?= $item['level'] ?>"><a href="#<?= htmlspecialchars($item['id']) ?>"><?= htmlspecialchars($item['text']) ?></a></li>
<?php endforeach; ?>
                    </ol>
                </nav>
            </aside>
<?php endif; ?>
            <article>
            <h1><?= htmlspecialchars($entryTitle) ?></h1>
<?php if ($draft || ($dateISO !== '' && $dateISO > date('Y-m-d')) || $date !== '' || $author !== ''): ?>
            <div class="entry-meta">
<?php if ($draft): ?>
                <span class="badge badge-draft" data-ui-key="draft"><?= htmlspecialchars($t('draft')) ?></span>
<?php endif; ?>
<?php if ($dateISO !== '' && $dateISO > date('Y-m-d')): ?>
                <span class="badge badge-future" data-ui-key="scheduled" data-ui-params="<?= htmlspecialchars((string) json_encode(['date' => $date], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($t('scheduled', ['date' => $date])) ?></span>
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
                        <a href="<?= $rootPath ?>tags/<?= htmlspecialchars($tag) ?>/" class="tag-link">#<?= htmlspecialchars($tag) ?></a>
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
<?php if (!empty($translations)): ?>
                <section class="translations" aria-label="<?= htmlspecialchars($t('other_languages')) ?>" data-ui-attr-aria-label="other_languages">
                    <h2 data-ui-key="other_languages"><?= htmlspecialchars($t('other_languages')) ?></h2>
                    <ul>
<?php foreach ($translations as $translation): ?>
                        <li><a hreflang="<?= htmlspecialchars($translation->language) ?>" href="<?= htmlspecialchars($translation->permalink) ?>"><?= htmlspecialchars($translation->language) ?>: <?= htmlspecialchars($translation->title) ?></a></li>
<?php endforeach; ?>
                    </ul>
                </section>
<?php endif; ?>
<?php if (!empty($related)): ?>
                <section class="related" aria-label="<?= htmlspecialchars($t('related_posts')) ?>" data-ui-attr-aria-label="related_posts">
                    <h2 data-ui-key="related_posts"><?= htmlspecialchars($t('related_posts')) ?></h2>
                    <ul>
<?php foreach ($related as $item): ?>
                        <li>
                            <a href="<?= htmlspecialchars($item->permalink) ?>"><?= htmlspecialchars($item->title) ?></a>
<?php if ($item->date !== null): ?>
                            <time datetime="<?= htmlspecialchars($item->date->format('Y-m-d')) ?>"><?= htmlspecialchars($item->date->format('Y-m-d')) ?></time>
<?php endif; ?>
                        </li>
<?php endforeach; ?>
                    </ul>
                </section>
<?php endif; ?>
            </article>
<?php if ($hasSidebar): ?>
        </div>
<?php endif; ?>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath, 'ui' => $ui, 'uiLanguage' => $uiLanguage]) ?>
</body>
</html>

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
 * @var array<string, mixed> $extra
 * @var bool $showTitle
 * @var string $headAssets
 * @var list<array{id: string, text: string, level: int}> $toc
 * @var list<YiiPress\Content\Model\RelatedEntry> $related
 * @var list<YiiPress\Content\Model\Translation> $translations
 * @var array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null $navigationPager
 * @var string $language
 * @var string $permalink
 * @var ?Navigation $nav
 * @var Closure(string, array): string $partial
 * @var string $rootPath
 * @var YiiPress\Build\MetaTags $metaTags
 * @var bool $search
 * @var int $searchResults
 * @var string $uiLanguage
 * @var list<string> $uiLanguages
 * @var array<string, array<string, string>> $uiCatalogs
 * @var YiiPress\I18n\UiText $ui
 * @var Closure(string, int, ?string, bool): string $h
 * @var Closure(string, array): string $t
 */

use YiiPress\Content\Model\Navigation;
use YiiPress\Render\NavigationRenderer;
$language ??= 'en';
$uiLanguage ??= 'en';
$translations ??= [];
$permalink ??= '';
$hasToc = $toc !== [];
$hasDocsSidebar = $nav !== null && NavigationRenderer::menuContainsUrl($nav, 'sidebar', $permalink);
$useDocsLayout = $hasDocsSidebar;
$useLegacyTocSidebar = !$useDocsLayout && $hasToc;
?>
<!DOCTYPE html>
<html lang="<?= $h($language) ?>">
<head>

<?= $partial('head', ['title' => $entryTitle . ' — ' . $siteTitle, 'rootPath' => $rootPath, 'headAssets' => $headAssets ?? '', 'metaTags' => $metaTags, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage], 'uiCatalogs' => $uiCatalogs ?? [$uiLanguage => []]]) ?>

</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage]]) ?>
<main>
    <div class="container">
<?php if ($useDocsLayout): ?>
        <div class="docs-layout<?= $hasToc ? ' docs-layout-with-toc' : '' ?>">
            <aside class="docs-sidebar" aria-label="<?= $h($t('sidebar_navigation')) ?>" data-ui-attr-aria-label="sidebar_navigation">
                <?= NavigationRenderer::render($nav, 'sidebar', $rootPath, $uiLanguage, $uiLanguage, 'docs-sidebar-nav', $permalink) ?>
            </aside>
<?php elseif ($useLegacyTocSidebar): ?>
        <div class="article-with-sidebar">
<?php endif; ?>
<?php if ($useLegacyTocSidebar): ?>
            <aside class="toc-sidebar" aria-label="<?= $h($t('table_of_contents')) ?>" data-ui-attr-aria-label="table_of_contents">
                <nav>
                    <ol>
<?php foreach ($toc as $item): ?>
                        <li class="toc-level-<?= $item['level'] ?>"><a href="#<?= $h($item['id']) ?>"><?= $h($item['text']) ?></a></li>
<?php endforeach; ?>
                    </ol>
                </nav>
            </aside>
<?php endif; ?>
            <article<?= $useDocsLayout ? ' class="docs-content"' : '' ?>>
<?php if ($showTitle): ?>
            <h1><?= $h($entryTitle) ?></h1>
<?php endif; ?>
<?php if ($draft || ($dateISO !== '' && $dateISO > date('Y-m-d')) || $date !== '' || $author !== ''): ?>
            <div class="entry-meta">
<?php if ($draft): ?>
                <span class="badge badge-draft" data-ui-key="draft"><?= $h($t('draft')) ?></span>
<?php endif; ?>
<?php if ($dateISO !== '' && $dateISO > date('Y-m-d')): ?>
                <span class="badge badge-future" data-ui-key="scheduled" data-ui-params="<?= $h((string) json_encode(['date' => $date], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $h($t('scheduled', ['date' => $date])) ?></span>
<?php elseif ($date !== ''): ?>
                <time datetime="<?= $h($dateISO) ?>"><?= $h($date) ?></time>
<?php endif; ?>
<?php if ($author !== ''): ?>
                <span class="author"><?= $h($author) ?></span>
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
                        <a href="<?= $h($rootPath) ?>tags/<?= $h($tag) ?>/" class="tag-link">#<?= $h($tag) ?></a>
<?php endforeach; ?>
                    </div>
<?php endif; ?>
<?php if ($categories !== []): ?>
                    <div class="entry-categories">
<?php foreach ($categories as $category): ?>
                        <a href="<?= $h($rootPath) ?>categories/<?= $h($category) ?>/" class="category"><?= $h($category) ?></a>
<?php endforeach; ?>
                    </div>
<?php endif; ?>
                </footer>
<?php endif; ?>
<?php if (!empty($translations)): ?>
                <section class="translations" aria-label="<?= $h($t('other_languages')) ?>" data-ui-attr-aria-label="other_languages">
                    <h2 data-ui-key="other_languages"><?= $h($t('other_languages')) ?></h2>
                    <ul>
<?php foreach ($translations as $translation): ?>
                        <li><a hreflang="<?= $h($translation->language) ?>" href="<?= $h($translation->permalink) ?>"><?= $h($translation->language) ?>: <?= $h($translation->title) ?></a></li>
<?php endforeach; ?>
                    </ul>
                </section>
<?php endif; ?>
<?php if (!empty($related)): ?>
                <section class="related" aria-label="<?= $h($t('related_posts')) ?>" data-ui-attr-aria-label="related_posts">
                    <h2 data-ui-key="related_posts"><?= $h($t('related_posts')) ?></h2>
                    <ul>
<?php foreach ($related as $item): ?>
                        <li>
                            <a href="<?= $h($item->permalink) ?>"><?= $h($item->title) ?></a>
<?php if ($item->date !== null): ?>
                            <time datetime="<?= $h($item->date->format('Y-m-d')) ?>"><?= $h($item->date->format('Y-m-d')) ?></time>
<?php endif; ?>
                        </li>
<?php endforeach; ?>
                    </ul>
                </section>
<?php endif; ?>
<?php if ($navigationPager !== null && ($navigationPager['previous'] !== null || $navigationPager['next'] !== null)): ?>
                <nav class="entry-pager" aria-label="<?= $h($t('pagination')) ?>" data-ui-attr-aria-label="pagination">
<?php if ($navigationPager['previous'] !== null): ?>
                    <a class="entry-pager-link entry-pager-previous" href="<?= $h($navigationPager['previous']['url']) ?>" rel="prev">
                        <span class="entry-pager-direction" data-ui-key="previous_page"><?= $h($t('previous_page')) ?></span>
                        <span class="entry-pager-title"><?= $h($navigationPager['previous']['title']) ?></span>
                    </a>
<?php else: ?>
                    <span></span>
<?php endif; ?>
<?php if ($navigationPager['next'] !== null): ?>
                    <a class="entry-pager-link entry-pager-next" href="<?= $h($navigationPager['next']['url']) ?>" rel="next">
                        <span class="entry-pager-direction" data-ui-key="next_page"><?= $h($t('next_page')) ?></span>
                        <span class="entry-pager-title"><?= $h($navigationPager['next']['title']) ?></span>
                    </a>
<?php endif; ?>
                </nav>
<?php endif; ?>
            </article>
<?php if ($useDocsLayout && $hasToc): ?>
            <aside class="toc-sidebar toc-sidebar-right" aria-label="<?= $h($t('table_of_contents')) ?>" data-ui-attr-aria-label="table_of_contents">
                <nav>
                    <ol>
<?php foreach ($toc as $item): ?>
                        <li class="toc-level-<?= $item['level'] ?>"><a href="#<?= $h($item['id']) ?>"><?= $h($item['text']) ?></a></li>
<?php endforeach; ?>
                    </ol>
                </nav>
            </aside>
<?php endif; ?>
<?php if ($useDocsLayout || $useLegacyTocSidebar): ?>
        </div>
<?php endif; ?>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath, 'ui' => $ui, 'uiLanguage' => $uiLanguage]) ?>
</body>
</html>

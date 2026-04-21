<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $collectionTitle
 * @var string $collectionName
 * @var list<array{title: string, url: string, date: string, dateISO: string, draft: bool, summary: string}> $entries
 * @var array{currentPage: int, totalPages: int, previousUrl: string, nextUrl: string} $pagination
 * @var ?Navigation $nav
 * @var Closure(string, array): string $partial
 * @var string $language
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
$pageTitle = $collectionTitle
    . ($pagination['currentPage'] > 1 ? ' — ' . $t('page_number', ['page' => $pagination['currentPage']]) : '')
    . ' — ' . $siteTitle;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language) ?>">
<head>
<?= $partial('head', ['title' => $pageTitle, 'rootPath' => $rootPath, 'collectionName' => $collectionName, 'metaTags' => $metaTags, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage], 'uiCatalogs' => $uiCatalogs ?? [$uiLanguage => []]]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage]]) ?>
<main>
    <div class="container">
        <div class="collection-header">
            <h1><?= htmlspecialchars($collectionTitle) ?></h1>
            <div class="collection-actions">
                <a href="<?= $rootPath . htmlspecialchars($collectionName) ?>/archive/" class="archive-link" title="<?= htmlspecialchars($t('archive_browse_by_date')) ?>" data-ui-attr-title="archive_browse_by_date" aria-label="<?= htmlspecialchars($t('archive')) ?>" data-ui-attr-aria-label="archive">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </a>
                <a href="<?= $rootPath . htmlspecialchars($collectionName) ?>/rss.xml" class="feed-link" title="<?= htmlspecialchars($t('rss_feed')) ?>" data-ui-attr-title="rss_feed" aria-label="<?= htmlspecialchars($t('rss_feed')) ?>" data-ui-attr-aria-label="rss_feed">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11a9 9 0 0 1 9 9"></path><path d="M4 4a16 16 0 0 1 16 16"></path><circle cx="5" cy="19" r="1"></circle></svg>
                </a>
            </div>
        </div>
<?php if ($entries === []): ?>
        <p data-ui-key="no_entries"><?= htmlspecialchars($t('no_entries')) ?></p>
<?php else: ?>
        <ul class="entry-list">
<?php foreach ($entries as $entry): ?>
            <li>
                <a href="<?= htmlspecialchars($entry['url']) ?>"><?= htmlspecialchars($entry['title']) ?></a>
<?php if ($entry['draft']): ?>
                <span class="badge badge-draft" data-ui-key="draft"><?= htmlspecialchars($t('draft')) ?></span>
<?php endif; ?>
<?php if ($entry['dateISO'] !== '' && $entry['dateISO'] > date('Y-m-d')): ?>
                <span class="badge badge-future" data-ui-key="scheduled" data-ui-params="<?= htmlspecialchars((string) json_encode(['date' => $entry['date']], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($t('scheduled', ['date' => $entry['date']])) ?></span>
<?php endif; ?>
<?php if ($entry['date'] !== '' && $entry['dateISO'] <= date('Y-m-d')): ?>
                <time><?= htmlspecialchars($entry['date']) ?></time>
<?php endif; ?>
<?php if ($entry['summary'] !== ''): ?>
                <p><?= htmlspecialchars($entry['summary']) ?></p>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
<?php if ($pagination['totalPages'] > 1): ?>
        <nav class="pagination">
<?php if ($pagination['previousUrl'] !== ''): ?>
            <a href="<?= htmlspecialchars($pagination['previousUrl']) ?>" rel="prev">&larr; <span data-ui-key="previous"><?= htmlspecialchars($t('previous')) ?></span></a>
<?php endif; ?>
            <span data-ui-key="page_of" data-ui-params="<?= htmlspecialchars((string) json_encode(['current' => $pagination['currentPage'], 'total' => $pagination['totalPages']], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($t('page_of', ['current' => $pagination['currentPage'], 'total' => $pagination['totalPages']])) ?></span>
<?php if ($pagination['nextUrl'] !== ''): ?>
            <a href="<?= htmlspecialchars($pagination['nextUrl']) ?>" rel="next"><span data-ui-key="next"><?= htmlspecialchars($t('next')) ?></span> &rarr;</a>
<?php endif; ?>
        </nav>
<?php endif; ?>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath, 'ui' => $ui, 'uiLanguage' => $uiLanguage]) ?>
</body>
</html>

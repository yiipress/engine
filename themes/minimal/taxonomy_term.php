<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $taxonomyName
 * @var string $term
 * @var list<array{title: string, url: string, date: string}> $entries
 * @var array{currentPage: int, totalPages: int, previousUrl: string, nextUrl: string} $pagination
 * @var ?Navigation $nav
 * @var Closure(string, array): string $partial
 * @var string $language
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
$language ??= 'en';
$uiLanguage ??= 'en';
$taxonomyLabel = $ui->taxonomyLabel($taxonomyName);
$taxonomyKey = 'taxonomy.' . strtolower($taxonomyName);
$pageTitle = $term . ' — ' . $taxonomyLabel
    . ($pagination['currentPage'] > 1 ? ' — ' . $t('page_number', ['page' => $pagination['currentPage']]) : '')
    . ' — ' . $siteTitle;
?>
<!DOCTYPE html>
<html lang="<?= $h($language) ?>">
<head>
<?= $partial('head', ['title' => $pageTitle, 'rootPath' => $rootPath, 'metaTags' => $metaTags, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage], 'uiCatalogs' => $uiCatalogs ?? [$uiLanguage => []]]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage]]) ?>
<main>
    <div class="container">
        <h1><span data-ui-key="<?= $h($taxonomyKey) ?>"><?= $h($taxonomyLabel) ?></span>: <?= $h($term) ?></h1>
        <ul class="entry-list">
<?php foreach ($entries as $entry): ?>
            <li>
                <a href="<?= $h($entry['url']) ?>"><?= $h($entry['title']) ?></a>
<?php if ($entry['date'] !== ''): ?>
                <time datetime="<?= $h($entry['date']) ?>"><?= $h($entry['date']) ?></time>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ul>
<?php if ($pagination['totalPages'] > 1): ?>
        <nav class="pagination">
<?php if ($pagination['previousUrl'] !== ''): ?>
            <a href="<?= $h($pagination['previousUrl']) ?>" rel="prev">&larr; <span data-ui-key="previous"><?= $h($t('previous')) ?></span></a>
<?php endif; ?>
            <span data-ui-key="page_of" data-ui-params="<?= $h((string) json_encode(['current' => $pagination['currentPage'], 'total' => $pagination['totalPages']], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $h($t('page_of', ['current' => $pagination['currentPage'], 'total' => $pagination['totalPages']])) ?></span>
<?php if ($pagination['nextUrl'] !== ''): ?>
            <a href="<?= $h($pagination['nextUrl']) ?>" rel="next"><span data-ui-key="next"><?= $h($t('next')) ?></span> &rarr;</a>
<?php endif; ?>
        </nav>
<?php endif; ?>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath, 'ui' => $ui, 'uiLanguage' => $uiLanguage]) ?>
</body>
</html>

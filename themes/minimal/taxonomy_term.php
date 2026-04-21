<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $taxonomyName
 * @var string $term
 * @var list<array{title: string, url: string, date: string}> $entries
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
 * @var Closure(string, int, ?string, bool): string $h
 */

use App\Content\Model\Navigation;
$language ??= 'en';
$uiLanguage ??= 'en';
$taxonomyLabel = $ui->taxonomyLabel($taxonomyName);
$taxonomyKey = 'taxonomy.' . strtolower($taxonomyName);
?>
<!DOCTYPE html>
<html lang="<?= $h($language) ?>">
<head>
<?= $partial('head', ['title' => $term . ' — ' . $taxonomyLabel . ' — ' . $siteTitle, 'rootPath' => $rootPath, 'metaTags' => $metaTags, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage], 'uiCatalogs' => $uiCatalogs ?? [$uiLanguage => []]]) ?>
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
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath, 'ui' => $ui, 'uiLanguage' => $uiLanguage]) ?>
</body>
</html>

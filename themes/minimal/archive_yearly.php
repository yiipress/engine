<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $collectionName
 * @var string $collectionTitle
 * @var string $year
 * @var list<string> $months
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
 */

use App\Content\Model\Navigation;
$language ??= 'en';
$uiLanguage ??= 'en';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language) ?>">
<head>
<?= $partial('head', ['title' => $collectionTitle . ': ' . $year . ' — ' . $siteTitle, 'rootPath' => $rootPath, 'metaTags' => $metaTags, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage], 'uiCatalogs' => $uiCatalogs ?? [$uiLanguage => []]]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage]]) ?>
<main>
    <div class="container">
        <h1><?= htmlspecialchars($collectionTitle) ?>: <?= $year ?></h1>
        <nav class="archive-months">
            <ul>
<?php foreach ($months as $m): ?>
<?php $month = (string) $m; ?>
                <li><a href="<?= $rootPath . htmlspecialchars($collectionName) ?>/<?= $year ?>/<?= $month ?>/"><span data-ui-month="<?= htmlspecialchars($month) ?>"><?= htmlspecialchars($ui->monthName((int) $month)) ?></span></a></li>
<?php endforeach; ?>
            </ul>
        </nav>
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
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath, 'ui' => $ui, 'uiLanguage' => $uiLanguage]) ?>
</body>
</html>

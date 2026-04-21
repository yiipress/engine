<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $taxonomyName
 * @var list<string> $terms
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
$taxonomyLabel = $ui->taxonomyLabel($taxonomyName);
$taxonomyKey = 'taxonomy.' . strtolower($taxonomyName);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language) ?>">
<head>
<?= $partial('head', ['title' => $taxonomyLabel . ' — ' . $siteTitle, 'rootPath' => $rootPath, 'metaTags' => $metaTags, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage], 'uiCatalogs' => $uiCatalogs ?? [$uiLanguage => []]]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage]]) ?>
<main>
    <div class="container">
        <h1 data-ui-key="<?= htmlspecialchars($taxonomyKey) ?>"><?= htmlspecialchars($taxonomyLabel) ?></h1>
        <ul class="term-list">
<?php foreach ($terms as $term): ?>
            <li><a href="<?= $rootPath . htmlspecialchars($taxonomyName) ?>/<?= htmlspecialchars($term) ?>/"><?= htmlspecialchars($term) ?></a></li>
<?php endforeach; ?>
        </ul>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath, 'ui' => $ui, 'uiLanguage' => $uiLanguage]) ?>
</body>
</html>

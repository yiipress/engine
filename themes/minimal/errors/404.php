<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var Navigation|null $nav
 * @var Closure(string, array): string $partial
 * @var string $language
 * @var string $rootPath
 * @var bool $search
 * @var int $searchResults
 * @var string $uiLanguage
 * @var list<string> $uiLanguages
 * @var array<string, array<string, string>> $uiCatalogs
 * @var App\I18n\UiText $ui
 * @var Closure(string, int, ?string, bool): string $h
 * @var Closure(string, array): string $t
 */

use App\Content\Model\Navigation;
$language ??= 'en';
$uiLanguage ??= 'en';
?>
<!DOCTYPE html>
<html lang="<?= $h($language) ?>">
<head>
<?= $partial('head', ['title' => $t('page_not_found') . ' — ' . $siteTitle, 'rootPath' => $rootPath, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage], 'uiCatalogs' => $uiCatalogs ?? [$uiLanguage => []]]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage]]) ?>
<main>
    <div class="container">
        <div class="error-page">
            <h1>404</h1>
            <p data-ui-key="page_not_found_description"><?= $h($t('page_not_found_description')) ?></p>
            <p><a href="<?= $h($rootPath) ?>" data-ui-key="go_to_home_page"><?= $h($t('go_to_home_page')) ?></a></p>
        </div>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath, 'ui' => $ui, 'uiLanguage' => $uiLanguage]) ?>
</body>
</html>

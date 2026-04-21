<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var list<array{title: string, url: string, avatar: string}> $authorList
 * @var ?Navigation $nav
 * @var Closure(string, array): string $partial
 * @var string $rootPath
 */

use App\Content\Model\Navigation;
use App\I18n\UiText;

$language ??= 'en';
$uiLanguage ??= 'en';
$ui ??= UiText::for($uiLanguage);
$t ??= static fn (string $key, array $params = []): string => $ui->get($key, $params);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language) ?>">
<head>
<?= $partial('head', ['title' => $t('authors') . ' — ' . $siteTitle, 'rootPath' => $rootPath, 'metaTags' => $metaTags, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage], 'uiCatalogs' => $uiCatalogs ?? [$uiLanguage => []]]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage]]) ?>
<main>
    <div class="container">
        <h1 data-ui-key="authors"><?= htmlspecialchars($t('authors')) ?></h1>
        <ul class="author-grid">
<?php foreach ($authorList as $author): ?>
            <li>
<?php if ($author['avatar'] !== ''): ?>
                <img src="<?= htmlspecialchars($author['avatar']) ?>" alt="<?= htmlspecialchars($author['title']) ?>">
<?php endif; ?>
                <a href="<?= htmlspecialchars($author['url']) ?>"><?= htmlspecialchars($author['title']) ?></a>
            </li>
<?php endforeach; ?>
        </ul>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath, 'ui' => $ui, 'uiLanguage' => $uiLanguage]) ?>
</body>
</html>

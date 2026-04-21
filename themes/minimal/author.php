<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $authorTitle
 * @var string $authorEmail
 * @var string $authorUrl
 * @var string $authorAvatar
 * @var string $authorBio
 * @var list<array{title: string, url: string, date: string}> $entries
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
<?= $partial('head', ['title' => $authorTitle . ' — ' . $siteTitle, 'rootPath' => $rootPath, 'metaTags' => $metaTags, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage], 'uiCatalogs' => $uiCatalogs ?? [$uiLanguage => []]]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10, 'ui' => $ui, 'uiLanguage' => $uiLanguage, 'uiLanguages' => $uiLanguages ?? [$uiLanguage]]) ?>
<main>
    <div class="container">
        <div class="author-profile">
<?php if ($authorAvatar !== ''): ?>
            <img src="<?= htmlspecialchars($authorAvatar) ?>" alt="<?= htmlspecialchars($authorTitle) ?>">
<?php endif; ?>
            <div class="author-info">
                <h1><?= htmlspecialchars($authorTitle) ?></h1>
<?php if ($authorEmail !== '' || $authorUrl !== ''): ?>
                <div class="author-links">
<?php if ($authorEmail !== ''): ?>
                    <a href="mailto:<?= htmlspecialchars($authorEmail) ?>"><?= htmlspecialchars($authorEmail) ?></a>
<?php endif; ?>
<?php if ($authorUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($authorUrl) ?>"><?= htmlspecialchars($authorUrl) ?></a>
<?php endif; ?>
                </div>
<?php endif; ?>
<?php if ($authorBio !== ''): ?>
                <div class="bio content"><?= $authorBio ?></div>
<?php endif; ?>
            </div>
        </div>
<?php if ($entries !== []): ?>
        <h2 data-ui-key="posts"><?= htmlspecialchars($t('posts')) ?></h2>
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
<?php endif; ?>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath, 'ui' => $ui, 'uiLanguage' => $uiLanguage]) ?>
</body>
</html>

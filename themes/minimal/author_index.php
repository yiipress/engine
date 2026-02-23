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

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= $partial('head', ['title' => 'Authors — ' . $siteTitle, 'rootPath' => $rootPath]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath]) ?>
<main>
    <div class="container">
        <h1>Authors</h1>
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
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath]) ?>
</body>
</html>

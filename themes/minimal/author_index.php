<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var list<array{title: string, url: string, avatar: string}> $authorList
 * @var ?\App\Content\Model\Navigation $nav
 * @var Closure(string, array): string $partial
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= $partial('head', ['title' => 'Authors — ' . $siteTitle]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav]) ?>
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
<?= $partial('footer', ['nav' => $nav]) ?>
</body>
</html>

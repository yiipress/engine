<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var string $taxonomyName
 * @var list<string> $terms
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(ucfirst($taxonomyName)) ?> — <?= htmlspecialchars($siteTitle) ?></title>
</head>
<body>
<header>
    <nav><a href="/"><?= htmlspecialchars($siteTitle) ?></a></nav>
</header>
<main>
    <h1><?= htmlspecialchars(ucfirst($taxonomyName)) ?></h1>
    <ul>
<?php foreach ($terms as $term): ?>
        <li><a href="/<?= htmlspecialchars($taxonomyName) ?>/<?= htmlspecialchars($term) ?>/"><?= htmlspecialchars($term) ?></a></li>
<?php endforeach; ?>
    </ul>
</main>
</body>
</html>

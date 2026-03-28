<?php

declare(strict_types=1);

/**
 * @var string $siteTitle
 * @var Navigation|null $nav
 * @var Closure(string, array): string $partial
 * @var string $rootPath
 */

use App\Content\Model\Navigation;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= $partial('head', ['title' => 'Page Not Found — ' . $siteTitle, 'rootPath' => $rootPath, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10]) ?>
</head>
<body>
<?= $partial('header', ['siteTitle' => $siteTitle, 'nav' => $nav, 'rootPath' => $rootPath, 'search' => $search ?? false, 'searchResults' => $searchResults ?? 10]) ?>
<main>
    <div class="container">
        <div class="error-page">
            <h1>404</h1>
            <p>The page you are looking for does not exist.</p>
            <p><a href="<?= $rootPath ?>">Go to home page</a></p>
        </div>
    </div>
</main>
<?= $partial('footer', ['nav' => $nav, 'rootPath' => $rootPath]) ?>
</body>
</html>

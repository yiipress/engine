<?php
/**
 * @var string $title
 * @var string $rootPath
 * @var string $headAssets
 * @var string|null $collectionName
 * @var MetaTags|null $metaTags
 * @var bool $search
 * @var int $searchResults
 * @var AssetFingerprintManifest|null $assetManifest
 */

use App\Build\AssetFingerprintManifest;
use App\Build\Asset;
use App\Build\MetaTags;

$headAssets ??= '';
$collectionName ??= null;
$metaTags ??= null;
$search ??= false;
$searchResults ??= 10;
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
<?php if ($metaTags !== null): ?>
<?php if ($metaTags->canonicalUrl !== ''): ?>
    <link rel="canonical" href="<?= htmlspecialchars($metaTags->canonicalUrl) ?>">
<?php endif; ?>
    <meta name="description" content="<?= htmlspecialchars($metaTags->description) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($metaTags->title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaTags->description) ?>">
    <meta property="og:type" content="<?= htmlspecialchars($metaTags->type) ?>">
<?php if ($metaTags->canonicalUrl !== ''): ?>
    <meta property="og:url" content="<?= htmlspecialchars($metaTags->canonicalUrl) ?>">
<?php endif; ?>
<?php if ($metaTags->image !== ''): ?>
    <meta property="og:image" content="<?= htmlspecialchars($metaTags->image) ?>">
<?php endif; ?>
    <meta name="twitter:card" content="<?= htmlspecialchars($metaTags->twitterCard) ?>">
<?php if ($metaTags->twitterSite !== ''): ?>
    <meta name="twitter:site" content="<?= htmlspecialchars($metaTags->twitterSite) ?>">
<?php endif; ?>
<?php endif; ?>
    <script>
        (function () {
            var root = document.documentElement;
            var theme = 'light';

            try {
                theme = localStorage.getItem('yiipress-theme')
                    || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            } catch (e) {
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    theme = 'dark';
                }
            }

            root.setAttribute('data-theme', theme);
            root.style.colorScheme = theme;
        })();
    </script>
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url('assets/theme/style.css', $rootPath, $assetManifest)) ?>">
<?php if ($collectionName !== null): ?>
    <link rel="alternate" type="application/rss+xml" title="RSS Feed" href="<?= $rootPath . htmlspecialchars($collectionName) ?>/rss.xml">
    <link rel="alternate" type="application/atom+xml" title="Atom Feed" href="<?= $rootPath . htmlspecialchars($collectionName) ?>/feed.xml">
<?php endif; ?>
    <script src="<?= htmlspecialchars(Asset::url('assets/theme/image-zoom.js', $rootPath, $assetManifest)) ?>" defer></script>
<?php if ($search): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url('assets/theme/search.css', $rootPath, $assetManifest)) ?>">
    <script src="<?= htmlspecialchars(Asset::url('assets/theme/search.js', $rootPath, $assetManifest)) ?>" defer></script>
<?php endif; ?>
<?= $headAssets ?>

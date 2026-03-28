<?php
/**
 * @var string $title
 * @var string $rootPath
 * @var string $headAssets
 * @var string|null $collectionName
 * @var \App\Build\MetaTags|null $metaTags
 * @var bool $search
 * @var int $searchResults
 */
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
    <link rel="stylesheet" href="<?= $rootPath ?>assets/theme/style.css">
<?php if ($collectionName !== null): ?>
    <link rel="alternate" type="application/rss+xml" title="RSS Feed" href="<?= $rootPath . htmlspecialchars($collectionName) ?>/rss.xml">
    <link rel="alternate" type="application/atom+xml" title="Atom Feed" href="<?= $rootPath . htmlspecialchars($collectionName) ?>/feed.xml">
<?php endif; ?>
    <script src="<?= $rootPath ?>assets/theme/image-zoom.js" defer></script>
<?php if ($search): ?>
    <link rel="stylesheet" href="<?= $rootPath ?>assets/theme/search.css">
    <script src="<?= $rootPath ?>assets/theme/search.js" defer></script>
<?php endif; ?>
<?= $headAssets ?>

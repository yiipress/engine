<?php
/**
 * @var string $title
 * @var string $rootPath
 * @var string $headAssets
 * @var string|null $collectionName
 */
$headAssets ??= '';
$collectionName ??= null;
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="<?= $rootPath ?>assets/theme/style.css">
<?php if ($collectionName !== null): ?>
    <link rel="alternate" type="application/rss+xml" title="RSS Feed" href="<?= $rootPath . htmlspecialchars($collectionName) ?>/rss.xml">
    <link rel="alternate" type="application/atom+xml" title="Atom Feed" href="<?= $rootPath . htmlspecialchars($collectionName) ?>/feed.xml">
<?php endif; ?>
    <script src="<?= $rootPath ?>assets/theme/image-zoom.js" defer></script>
<?= $headAssets ?>

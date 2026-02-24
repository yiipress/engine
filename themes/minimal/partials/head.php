<?php
/**
 * @var string $title
 * @var bool $hasMermaid
 * @var string $rootPath
 * @var string $headAssets
 */
$headAssets ??= '';
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="<?= $rootPath ?>assets/theme/style.css">
<?= $headAssets ?>

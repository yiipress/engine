<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run from CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$target = $argv[1] ?? $root . '/dist/yiipress.phar';
$targetDirectory = dirname($target);

if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
    fwrite(STDERR, "Failed to create target directory: {$targetDirectory}\n");
    exit(1);
}

if (file_exists($target)) {
    unlink($target);
}

$phar = new \Phar($target);
$phar->startBuffering();

$includeDirectories = [
    'config',
    'packages/highlighter-extension/php',
    'public',
    'src',
    'themes',
    'vendor',
];

foreach ($includeDirectories as $directory) {
    $path = $root . '/' . $directory;
    if (!is_dir($path)) {
        fwrite(STDERR, "Required directory is missing: {$directory}\n");
        exit(1);
    }

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
    );

    /** @var \SplFileInfo $file */
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $fullPath = $file->getPathname();
        $localPath = substr($fullPath, strlen($root) + 1);

        if (str_contains($localPath, '/.git/')) {
            continue;
        }

        $phar->addFile($fullPath, $localPath);
    }
}

$phar->addFile($root . '/yii', 'yii');
$phar->setStub(<<<'PHP'
#!/usr/bin/env php
<?php

Phar::mapPhar('yiipress.phar');
require 'phar://yiipress.phar/yii';
__HALT_COMPILER();
PHP);

$phar->stopBuffering();
chmod($target, 0755);

fwrite(STDOUT, "Built {$target}\n");

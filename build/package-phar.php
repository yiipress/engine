<?php

declare(strict_types=1);

use YiiPress\Build\PharArchiveFilter;
use YiiPress\Build\PhpDocStripper;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run from CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once __DIR__ . '/PharArchiveFilter.php';
require_once __DIR__ . '/PhpDocStripper.php';

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
        $localPath = str_replace('\\', '/', substr($fullPath, strlen($root) + 1));

        if (PharArchiveFilter::shouldExclude($localPath)) {
            continue;
        }

        if (PhpDocStripper::shouldStrip($localPath)) {
            $contents = file_get_contents($fullPath);
            if ($contents === false) {
                fwrite(STDERR, "Failed to read file: {$localPath}\n");
                exit(1);
            }

            $phar->addFromString($localPath, PhpDocStripper::strip($contents));
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

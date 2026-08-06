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
$commit = strtolower(getenv('YIIPRESS_COMMIT') ?: '');

if ($commit !== '' && preg_match('/^[0-9a-f]{40}$/', $commit) !== 1) {
    fwrite(STDERR, "YIIPRESS_COMMIT must be a full 40-character commit SHA.\n");
    exit(1);
}

if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
    fwrite(STDERR, "Failed to create target directory: {$targetDirectory}\n");
    exit(1);
}

if (file_exists($target)) {
    unlink($target);
}

$phar = new Phar($target);
$phar->startBuffering();

$includeDirectories = [
    'config',
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

        if ($localPath === 'src/ApplicationInfo.php' && $commit !== '') {
            $contents = file_get_contents($fullPath);
            if ($contents === false) {
                fwrite(STDERR, "Failed to read file: {$localPath}\n");
                exit(1);
            }
            $contents = str_replace(
                "public const string COMMIT = '';",
                "public const string COMMIT = '{$commit}';",
                $contents,
                $replacementCount,
            );
            if ($replacementCount !== 1) {
                fwrite(STDERR, "Failed to embed the YiiPress commit in {$localPath}.\n");
                exit(1);
            }
            $phar->addFromString($localPath, $contents);
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

$archivePrefix = 'phar://' . str_replace('\\', '/', $phar->getPath()) . '/';
foreach (new RecursiveIteratorIterator($phar) as $file) {
    $archivePath = str_replace('\\', '/', $file->getPathname());
    if (!str_starts_with($archivePath, $archivePrefix)) {
        fwrite(STDERR, "Could not resolve PHAR entry path: {$archivePath}\n");
        exit(1);
    }
    $archivePath = substr($archivePath, strlen($archivePrefix));
    if (PharArchiveFilter::shouldExclude($archivePath)) {
        fwrite(STDERR, "Excluded file was added to the PHAR: {$archivePath}\n");
        exit(1);
    }
}

foreach ([
    'config/environments/dev/params.php',
    'config/environments/test/params.php',
    'config/web/di/application.php',
    'vendor/symfony/console/Resources/bin/hiddeninput.exe',
    'vendor/symfony/console/Resources/completion.bash',
    'vendor/yiisoft/config/src/Composer/Options.php',
    'vendor/yiisoft/error-handler/templates/development.php',
    'vendor/yiisoft/yii-console/src/Command/Game.php',
    'vendor/yiisoft/yii-console/src/Command/Serve.php',
] as $requiredPath) {
    if (!isset($phar[$requiredPath])) {
        fwrite(STDERR, "Required runtime file is missing from the PHAR: {$requiredPath}\n");
        exit(1);
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

if (!Phar::canCompress(Phar::GZ)) {
    fwrite(STDERR, "Gzip PHAR compression requires the zlib extension.\n");
    exit(1);
}

$phar->compressFiles(Phar::GZ);
$phar->stopBuffering();
chmod($target, 0755);

fwrite(STDOUT, "Built {$target}\n");

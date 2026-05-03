<?php

declare(strict_types=1);

use App\Web\LiveReload\FileWatcher;
use App\Web\LiveReload\LiveReloadMiddleware;
use App\Web\LiveReload\SiteBuildRunner;
use App\Web\StaticFile\StaticFileAction;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

$packageRoot = dirname(__DIR__, 3);
$root = getcwd() ?: $packageRoot;
$yiiBinary = $packageRoot . '/yii';

if (str_starts_with(__FILE__, 'phar://')) {
    $yiiBinary = $_SERVER['argv'][0] ?? PHP_BINARY;
    if (!str_starts_with($yiiBinary, '/')) {
        $yiiBinary = $root . '/' . $yiiBinary;
    }
}

return [
    FileWatcher::class => static function () use ($root): FileWatcher {
        return new FileWatcher([
            $root . '/content',
            $root . '/themes',
        ]);
    },
    SiteBuildRunner::class => static function () use ($root, $yiiBinary): SiteBuildRunner {
        return new SiteBuildRunner(
            yiiBinary: $yiiBinary,
            contentDir: $root . '/content',
            outputDir: $root . '/output',
        );
    },
    StaticFileAction::class => static function (
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        SiteBuildRunner $buildRunner,
    ) use ($root): StaticFileAction {
        return new StaticFileAction($responseFactory, $streamFactory, $root . '/output', $buildRunner);
    },
    LiveReloadMiddleware::class => static function (StreamFactoryInterface $streamFactory): LiveReloadMiddleware {
        return new LiveReloadMiddleware($streamFactory);
    },
];

<?php

declare(strict_types=1);

use App\Environment;
use App\Web\LiveReload\FileWatcher;
use App\Web\LiveReload\LiveReloadMiddleware;
use App\Web\LiveReload\SiteBuildRunner;
use App\Web\StaticFile\StaticFileAction;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Yiisoft\Aliases\Aliases;

return [
    FileWatcher::class => static function (Aliases $aliases): FileWatcher {
        $root = $aliases->get('@root');

        return new FileWatcher([
            $root . '/content',
            $root . '/themes',
        ]);
    },
    SiteBuildRunner::class => static function (Aliases $aliases): SiteBuildRunner {
        $root = $aliases->get('@root');

        return new SiteBuildRunner(
            yiiBinary: $root . '/yii',
            contentDir: $root . '/content',
            outputDir: $root . '/output',
        );
    },
    StaticFileAction::class => static function (
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        SiteBuildRunner $buildRunner,
        Aliases $aliases,
    ): StaticFileAction {
        $root = $aliases->get('@root');

        return new StaticFileAction($responseFactory, $streamFactory, $root . '/output', $buildRunner);
    },
    LiveReloadMiddleware::class => static function (StreamFactoryInterface $streamFactory): LiveReloadMiddleware {
        return new LiveReloadMiddleware($streamFactory, Environment::isDev());
    },
];

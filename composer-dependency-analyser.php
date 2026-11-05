<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$root = __DIR__;
return (new Configuration())
    ->disableComposerAutoloadPathScan()
    ->setFileExtensions(['php'])
    ->addPathToScan($root . '/src', isDev: false)
    ->addPathToScan($root . '/config', isDev: false)
    ->addPathToScan($root . '/yii', isDev: false)
    ->addPathToScan($root . '/tests', isDev: true)
    // TarGzReader uses the production compress.zlib:// wrapper, which the function scan cannot detect.
    ->ignoreErrorsOnExtension('ext-zlib', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    // This optional test-only cache invalidation is guarded by function_exists().
    ->ignoreErrorsOnExtensionAndPath(
        'ext-zend-opcache',
        $root . '/tests/Functional/GetProcessorTest.php',
        [ErrorType::SHADOW_DEPENDENCY],
    )
    ->ignoreErrorsOnPackage('psr/container', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('yiisoft/config', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('yiisoft/di', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('yiisoft/yii-event', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('yiisoft/router-fastroute', [ErrorType::UNUSED_DEPENDENCY]);

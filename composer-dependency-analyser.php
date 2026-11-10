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
    ->ignoreErrorsOnPackage('psr/container', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('yiisoft/config', [ErrorType::UNUSED_DEPENDENCY])
    // The application runner creates the runtime DI container inside yiisoft/yii-runner.
    ->ignoreErrorsOnPackage('yiisoft/di', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    ->ignoreErrorsOnPackage('yiisoft/yii-event', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('yiisoft/router-fastroute', [ErrorType::UNUSED_DEPENDENCY]);

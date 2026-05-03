<?php

declare(strict_types=1);

use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Build\TemplateResolver;
use Yiisoft\Definitions\DynamicReference;

return [
    ThemeRegistry::class => DynamicReference::to(static function (): ThemeRegistry {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));
        return $registry;
    }),
    TemplateResolver::class => [
        'class' => TemplateResolver::class,
    ],
];

<?php

declare(strict_types=1);

use App\Build\Theme;
use App\Build\ThemeRegistry;
use App\Build\TemplateResolver;
use Yiisoft\Definitions\DynamicReference;

return [
    ThemeRegistry::class => DynamicReference::to(static function (): ThemeRegistry {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('default', dirname(__DIR__, 3) . '/templates'));
        return $registry;
    }),
    TemplateResolver::class => [
        'class' => TemplateResolver::class,
    ],
];

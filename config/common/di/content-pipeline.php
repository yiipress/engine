<?php

declare(strict_types=1);

use App\Build\TemplateResolver;
use App\Build\ThemeRegistry;
use App\Console\BuildCommand;
use App\Console\CleanCommand;
use App\Console\NewCommand;
use App\Processor\ContentProcessorPipeline;
use App\Processor\MarkdownProcessor;
use App\Processor\SyntaxHighlightProcessor;
use Yiisoft\Definitions\Reference;

return [
    'contentPipeline' => [
        'class' => ContentProcessorPipeline::class,
        '__construct()' => [
            new MarkdownProcessor(),
            new SyntaxHighlightProcessor(),
        ],
    ],
    'feedPipeline' => [
        'class' => ContentProcessorPipeline::class,
        '__construct()' => [
            new MarkdownProcessor(),
        ],
    ],
    BuildCommand::class => [
        '__construct()' => [
            'rootPath' => dirname(__DIR__, 3),
            'contentPipeline' => Reference::to('contentPipeline'),
            'feedPipeline' => Reference::to('feedPipeline'),
            'themeRegistry' => Reference::to(ThemeRegistry::class),
            'templateResolver' => Reference::to(TemplateResolver::class),
        ],
    ],
    CleanCommand::class => [
        '__construct()' => [
            'rootPath' => dirname(__DIR__, 3),
        ],
    ],
    NewCommand::class => [
        '__construct()' => [
            'rootPath' => dirname(__DIR__, 3),
        ],
    ],
];

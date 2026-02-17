<?php

declare(strict_types=1);

use App\Build\TemplateResolver;
use App\Build\ThemeRegistry;
use App\Console\BuildCommand;
use App\Console\CleanCommand;
use App\Console\NewCommand;
use App\Highlighter\SyntaxHighlighter;
use App\Processor\ContentProcessorPipeline;
use App\Processor\MarkdownProcessor;
use App\Processor\SyntaxHighlightProcessor;
use App\Render\MarkdownRenderer;
use Yiisoft\Definitions\Reference;

return [
    SyntaxHighlighter::class => [
        'class' => SyntaxHighlighter::class,
    ],
    'contentPipeline' => [
        'class' => ContentProcessorPipeline::class,
        '__construct()' => [
            Reference::to(MarkdownProcessor::class),
            Reference::to(SyntaxHighlightProcessor::class),
        ],
    ],
    'feedPipeline' => [
        'class' => ContentProcessorPipeline::class,
        '__construct()' => [
            Reference::to(MarkdownProcessor::class),
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

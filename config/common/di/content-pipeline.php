<?php

declare(strict_types=1);

use YiiPress\Build\TemplateResolver;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Console\BuildCommand;
use YiiPress\Console\CleanCommand;
use YiiPress\Console\InitCommand;
use YiiPress\Console\NewCommand;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\Mermaid\MermaidProcessor;
use YiiPress\Processor\OEmbed\OEmbedProcessor;
use YiiPress\Processor\Shortcode\ProjectShortcodeProcessor;
use YiiPress\Processor\Shortcode\TweetProcessor;
use YiiPress\Processor\Shortcode\VimeoProcessor;
use YiiPress\Processor\Shortcode\YouTubeProcessor;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Processor\SyntaxHighlightProcessor;
use YiiPress\Processor\TagLinkProcessor;
use YiiPress\Processor\Toc\TocProcessor;
use Psr\EventDispatcher\EventDispatcherInterface;
use Yiisoft\Definitions\Reference;

$workingDirectory = getcwd() ?: dirname(__DIR__, 3);

return [
    OEmbedProcessor::class => [
        'class' => OEmbedProcessor::class,
        '__construct()' => [
            Reference::to(YouTubeProcessor::class),
            Reference::to(VimeoProcessor::class),
            Reference::to(TweetProcessor::class),
        ],
    ],
    'contentPipeline' => [
        'class' => ContentProcessorPipeline::class,
        '__construct()' => [
            Reference::to(YouTubeProcessor::class),
            Reference::to(VimeoProcessor::class),
            Reference::to(TweetProcessor::class),
            Reference::to(ProjectShortcodeProcessor::class),
            Reference::to(OEmbedProcessor::class),
            Reference::to(MarkdownProcessor::class),
            Reference::to(TagLinkProcessor::class),
            Reference::to(MermaidProcessor::class),
            Reference::to(SyntaxHighlightProcessor::class),
            Reference::to(TocProcessor::class),
        ],
    ],
    'feedPipeline' => [
        'class' => ContentProcessorPipeline::class,
        '__construct()' => [
            Reference::to(ProjectShortcodeProcessor::class),
            Reference::to(MarkdownProcessor::class),
            Reference::to(TagLinkProcessor::class),
        ],
    ],
    BuildCommand::class => [
        '__construct()' => [
            'rootPath' => $workingDirectory,
            'contentPipeline' => Reference::to('contentPipeline'),
            'feedPipeline' => Reference::to('feedPipeline'),
            'themeRegistry' => Reference::to(ThemeRegistry::class),
            'templateResolver' => Reference::to(TemplateResolver::class),
            'eventDispatcher' => Reference::to(EventDispatcherInterface::class),
        ],
    ],
    CleanCommand::class => [
        '__construct()' => [
            'rootPath' => $workingDirectory,
        ],
    ],
    InitCommand::class => [
        '__construct()' => [
            'rootPath' => $workingDirectory,
        ],
    ],
    NewCommand::class => [
        '__construct()' => [
            'rootPath' => $workingDirectory,
        ],
    ],
];

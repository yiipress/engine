<?php

declare(strict_types=1);

use App\Build\TemplateResolver;
use App\Build\ThemeRegistry;
use App\Console\BuildCommand;
use App\Console\CleanCommand;
use App\Console\NewCommand;
use App\Processor\ContentProcessorPipeline;
use App\Processor\Mermaid\MermaidProcessor;
use App\Processor\OEmbed\OEmbedProcessor;
use App\Processor\Shortcode\TweetProcessor;
use App\Processor\Shortcode\VimeoProcessor;
use App\Processor\Shortcode\YouTubeProcessor;
use App\Processor\MarkdownProcessor;
use App\Processor\SyntaxHighlightProcessor;
use App\Processor\TagLinkProcessor;
use App\Processor\Toc\TocProcessor;
use Yiisoft\Definitions\Reference;

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

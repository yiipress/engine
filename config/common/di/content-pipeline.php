<?php

declare(strict_types=1);

use App\Console\BuildCommand;
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
            'contentPipeline' => Reference::to('contentPipeline'),
            'feedPipeline' => Reference::to('feedPipeline'),
        ],
    ],
];

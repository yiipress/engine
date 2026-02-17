<?php

declare(strict_types=1);

use App\Processor\ContentProcessorPipeline;
use App\Processor\MarkdownProcessor;
use App\Processor\SyntaxHighlightProcessor;

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
];

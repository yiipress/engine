<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

/**
 * Pipeline position where a processor entry point may run.
 */
enum ProcessorStage: string
{
    case BeforeMarkdown = 'before_markdown';
    case AfterMarkdown = 'after_markdown';
}

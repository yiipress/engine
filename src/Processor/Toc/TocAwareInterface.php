<?php

declare(strict_types=1);

namespace App\Processor\Toc;

/**
 * Processors that generate a table of contents implement this interface.
 * The pipeline collects TOC data from the first aware processor found.
 */
interface TocAwareInterface
{
    /**
     * Returns the table of contents entries extracted from the last processed content.
     *
     * @return list<array{id: string, text: string, level: int}>
     */
    public function getToc(): array;
}

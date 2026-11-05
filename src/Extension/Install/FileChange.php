<?php

declare(strict_types=1);

namespace YiiPress\Extension\Install;

/**
 * New contents of a project file, written inside the installation transaction.
 */
final readonly class FileChange
{
    /**
     * @param string $path Path relative to the project root, `/` separators.
     */
    public function __construct(
        public string $path,
        public string $contents,
    ) {}
}

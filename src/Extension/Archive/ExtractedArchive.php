<?php

declare(strict_types=1);

namespace YiiPress\Extension\Archive;

/**
 * Result of unpacking a release archive.
 */
final readonly class ExtractedArchive
{
    /**
     * @param string $commit Commit SHA from the pax global header, 40 lowercase hex characters.
     * @param string $root Directory holding `extension.yaml` and `package/`, the enclosing directory stripped.
     */
    public function __construct(
        public string $commit,
        public string $root,
    ) {}
}

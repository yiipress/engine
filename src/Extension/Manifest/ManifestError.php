<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

/**
 * One problem found in `extension.yaml`.
 */
final readonly class ManifestError
{
    /**
     * @param string $code Stable machine-readable code, for example `path_not_found`.
     * @param string $field Path of the field, for example `processor.entry_points[0].path`; empty for the whole file.
     * @param string $message Full sentence naming the field and the value.
     */
    public function __construct(
        public string $code,
        public string $field,
        public string $message,
    ) {}
}

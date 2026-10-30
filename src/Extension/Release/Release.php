<?php

declare(strict_types=1);

namespace YiiPress\Extension\Release;

/**
 * A stable GitHub release whose tag is a SemVer version.
 */
final readonly class Release
{
    /**
     * @param string $tag Tag exactly as on GitHub, for example `v1.2.0`.
     * @param string $version Tag without a leading `v`, for example `1.2.0`.
     */
    public function __construct(
        public string $tag,
        public string $version,
    ) {}
}

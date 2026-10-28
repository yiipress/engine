<?php

declare(strict_types=1);

namespace YiiPress\Extension;

use function preg_match;

/**
 * SemVer 2.0.0 `MAJOR.MINOR.PATCH[-PRERELEASE]` without a `v` prefix or build metadata.
 *
 * @internal
 */
final class SemanticVersion
{
    private const string IDENTIFIER = '(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)';
    private const string PATTERN = '/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)'
        . '(?:-' . self::IDENTIFIER . '(?:\.' . self::IDENTIFIER . ')*)?$/D';

    public static function isValid(string $version): bool
    {
        return preg_match(self::PATTERN, $version) === 1;
    }
}

<?php

declare(strict_types=1);

namespace YiiPress\Extension;

use function explode;
use function mb_check_encoding;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strstr;

/**
 * Checks that a relative path from a package is safe on Linux, macOS and Windows.
 *
 * Used for paths in `extension.yaml` and for entries of release archives.
 */
final class PortablePath
{
    private const int MAX_LENGTH = 255;
    private const int MAX_SEGMENT_LENGTH = 100;

    /**
     * Returns `null` when the path is portable, otherwise the reason it is not,
     * phrased to follow «path "…"», for example «must not be empty».
     */
    public static function problem(string $path): ?string
    {
        return self::wholePathProblem($path) ?? self::segmentsProblem($path);
    }

    private static function wholePathProblem(string $path): ?string
    {
        return match (true) {
            $path === '' => 'must not be empty',
            !mb_check_encoding($path, 'UTF-8') => 'must be valid UTF-8',
            preg_match('/[\x00-\x1F\x7F]/', $path) === 1 => 'must not contain control characters',
            str_contains($path, '\\') => 'must use "/" as a separator, not "\\"',
            str_starts_with($path, '/') => 'must be relative and must not start with "/"',
            preg_match('/[<>:"|?*]/', $path) === 1 => 'must not contain any of <>:"|?*',
            strlen($path) > self::MAX_LENGTH => sprintf('must not be longer than %d bytes', self::MAX_LENGTH),
            default => null,
        };
    }

    private static function segmentsProblem(string $path): ?string
    {
        foreach (explode('/', $path) as $segment) {
            $problem = self::segmentProblem($segment);
            if ($problem !== null) {
                return $problem;
            }
        }

        return null;
    }

    private static function segmentProblem(string $segment): ?string
    {
        $stem = strstr($segment, '.', true);

        return match (true) {
            $segment === '' || $segment === '.' || $segment === '..'
                => 'must not contain empty, "." or ".." segments',
            strlen($segment) > self::MAX_SEGMENT_LENGTH
                => sprintf('must not have a segment longer than %d bytes', self::MAX_SEGMENT_LENGTH),
            ReservedName::isReserved($stem === false ? $segment : $stem)
                => sprintf('must not use the reserved Windows name "%s"', $segment),
            str_ends_with($segment, '.') || str_ends_with($segment, ' ')
                => 'must not have a segment that ends in a dot or a space',
            default => null,
        };
    }
}

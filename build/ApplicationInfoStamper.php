<?php

declare(strict_types=1);

namespace YiiPress\Build;

use RuntimeException;

use function preg_match;
use function str_replace;

/**
 * Embeds the build commit and the release it is built on into `src/ApplicationInfo.php` of a nightly package.
 */
final class ApplicationInfoStamper
{
    private const string BASE_VERSION_PATTERN = '/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)$/D';

    /**
     * @param string $commit Full commit SHA of the build.
     * @param string $baseVersion Latest release tag the commit is built on, empty when unknown.
     */
    public static function stamp(string $contents, string $commit, string $baseVersion): string
    {
        if ($baseVersion !== '' && preg_match(self::BASE_VERSION_PATTERN, $baseVersion) !== 1) {
            throw new RuntimeException('YIIPRESS_BASE_VERSION must be a release version such as 0.1.13.');
        }

        $contents = str_replace(
            "public const string COMMIT = '';",
            "public const string COMMIT = '{$commit}';",
            $contents,
            $replacementCount,
        );
        if ($replacementCount !== 1) {
            throw new RuntimeException('Failed to embed the YiiPress commit in src/ApplicationInfo.php.');
        }

        $contents = str_replace(
            "public const string BASE_VERSION = '';",
            "public const string BASE_VERSION = '{$baseVersion}';",
            $contents,
            $replacementCount,
        );
        if ($replacementCount !== 1) {
            throw new RuntimeException('Failed to embed the YiiPress base version in src/ApplicationInfo.php.');
        }

        return $contents;
    }
}

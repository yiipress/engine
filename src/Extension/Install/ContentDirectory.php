<?php

declare(strict_types=1);

namespace YiiPress\Extension\Install;

use function array_filter;
use function array_values;
use function explode;
use function implode;
use function in_array;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * The content directory as a path relative to the project root, the form transactions and the lock file use.
 */
final class ContentDirectory
{
    /**
     * @param string $contentDir Relative to the project root, or absolute inside it.
     * @param string $outsideReason What cannot be done in a content directory outside the project.
     * @param string $solution What to do instead when the directory cannot be used.
     * @return string Relative path with a trailing `/`, or empty for the project root.
     *
     * @throws InstallException `config_unsupported` for a directory outside the project or with `..`.
     */
    public static function relative(
        string $contentDir,
        string $projectRoot,
        string $outsideReason,
        string $solution,
    ): string {
        $dir = rtrim($contentDir, '/');
        if (str_starts_with($dir, '/')) {
            $root = rtrim($projectRoot, '/') . '/';
            if ($dir . '/' === $root) {
                return '';
            }
            if (!str_starts_with($dir, $root)) {
                throw new InstallException('config_unsupported', sprintf(
                    'The content directory "%s" is outside the project "%s", so %s.',
                    $contentDir,
                    $projectRoot,
                    $outsideReason,
                ), solution: $solution);
            }
            $dir = substr($dir, strlen($root));
        }
        $segments = array_values(array_filter(
            explode('/', $dir),
            static fn(string $segment): bool => $segment !== '' && $segment !== '.',
        ));
        if (in_array('..', $segments, true)) {
            throw new InstallException('config_unsupported', sprintf(
                'The content directory "%s" must not contain "..".',
                $contentDir,
            ), solution: $solution);
        }

        return $segments === [] ? '' : implode('/', $segments) . '/';
    }
}

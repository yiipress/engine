<?php

declare(strict_types=1);

namespace YiiPress\Build;

use function basename;
use function explode;
use function in_array;
use function strtolower;
use function str_contains;
use function str_ends_with;
use function str_starts_with;

final class PharArchiveFilter
{
    public static function shouldExclude(string $path): bool
    {
        if (str_starts_with($path, 'runtime/')) {
            return true;
        }

        if (!str_starts_with($path, 'vendor/')) {
            return false;
        }

        if (str_starts_with($path, 'vendor/composer/')) {
            return false;
        }

        if (str_starts_with($path, 'vendor/bin/')) {
            return true;
        }

        $segments = explode('/', $path);
        $basename = basename($path);
        $lowerBasename = strtolower($basename);
        $lowerPath = strtolower($path);

        foreach ($segments as $segment) {
            $lowerSegment = strtolower($segment);
            if (in_array($lowerSegment, [
                '.git',
                '.github',
                '.phan',
                '.vscode',
                'benchmark',
                'benchmarks',
                'doc',
                'docs',
                'example',
                'examples',
                'fixture',
                'fixtures',
                'test',
                'tests',
                'tools',
            ], true)) {
                return true;
            }
        }

        if (str_starts_with($basename, '.') || $lowerBasename === 'makefile') {
            return true;
        }

        if (str_starts_with($lowerBasename, 'license') || str_starts_with($lowerBasename, 'licence')) {
            return false;
        }

        if (str_ends_with($lowerBasename, '.md') || str_ends_with($lowerBasename, '.rst')) {
            return true;
        }

        if (in_array($lowerBasename, [
            'composer.json',
            'composer.lock',
            'composer-require-check.json',
            'composer-require-checker.json',
            'context7.json',
            'ecs.php',
            'infection.json.dist',
            'package.json',
            'package-lock.json',
            'phpbench.json',
            'phpcs.xml',
            'phpcs.xml.dist',
            'phpdoc.dist.xml',
            'phpunit.xml',
            'phpunit.xml.dist',
            'rector-migrate.php',
            'rector.php',
            'renovate.json',
            'roave-bc-check.yaml',
        ], true)) {
            return true;
        }

        return str_starts_with($lowerBasename, 'psalm')
            || str_starts_with($lowerBasename, 'phpstan')
            || str_starts_with($lowerBasename, 'phpunit')
            || str_contains($lowerPath, '/.php-cs-fixer');
    }
}

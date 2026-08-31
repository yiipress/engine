<?php

declare(strict_types=1);

namespace YiiPress\Build;

use Phar;

use function constant;
use function dirname;
use function is_executable;

/**
 * Resolves the command used to re-invoke the currently running application (self-contained
 * static binary, plain phar run through a system PHP, or the dev `yii` script) as an
 * independent worker process.
 */
final class WorkerExecutable
{
    /** @return list<string> */
    public static function resolve(): array
    {
        $pharPath = Phar::running(false);

        if ($pharPath === '') {
            // Running from source (dev): php <package-root>/yii ...
            return [\PHP_BINARY, dirname(__DIR__, 2) . '/yii'];
        }

        // This process is already running as $pharPath, so it must be directly executable
        // on its own (a self-contained static binary, or a .phar with a shebang and the
        // executable bit set) -- prefer that over PHP_BINARY, which some SAPIs (e.g.
        // static-php-cli's micro "fake CLI") leave empty or otherwise unusable at runtime,
        // even though PHP's own stubs declare it non-empty. Read it dynamically so that
        // possibility is actually checked instead of assumed away. Only a plain .phar
        // without the executable bit needs a separate interpreter in front of it, and only
        // if PHP_BINARY is actually usable for that -- an empty one would otherwise produce
        // a command with no program name at all.
        /** @var string $phpBinary */
        $phpBinary = constant('PHP_BINARY');
        if (is_executable($pharPath) || $phpBinary === '') {
            return [$pharPath];
        }

        return [$phpBinary, $pharPath];
    }
}

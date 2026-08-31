<?php

declare(strict_types=1);

namespace YiiPress\Build;

use Phar;

use function dirname;
use function realpath;

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

        // A self-contained static binary combines the PHP runtime and the phar into one
        // executable, so PHP_BINARY *is* that file and already re-invokes the whole app;
        // a plain .phar is instead run through a separate PHP interpreter and needs its
        // path passed explicitly. realpath() on both sides tells them apart reliably,
        // regardless of how this process was invoked (PATH lookup, relative path, symlink).
        $realPharPath = realpath($pharPath);
        $realPhpBinary = realpath(\PHP_BINARY);

        if ($realPharPath !== false && $realPharPath === $realPhpBinary) {
            return [\PHP_BINARY];
        }

        return [\PHP_BINARY, $pharPath];
    }
}

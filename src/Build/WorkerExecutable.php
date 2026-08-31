<?php

declare(strict_types=1);

namespace YiiPress\Build;

use function basename;
use function getcwd;
use function preg_match;
use function str_ends_with;
use function str_starts_with;
use function strtolower;

/**
 * Resolves the command used to re-invoke the currently running application (phar, dev `yii`
 * script, or a plain PHP script) as an independent worker process.
 */
final class WorkerExecutable
{
    /** @return list<string> */
    public static function resolve(): array
    {
        $arguments = $_SERVER['argv'] ?? [];
        /** @var list<string> $arguments */
        $script = $arguments[0] ?? '';
        if ($script !== '' && !str_starts_with($script, '/') && !preg_match('~^[A-Za-z]:[\\\\/]~', $script)) {
            $script = (getcwd() ?: '.') . \DIRECTORY_SEPARATOR . $script;
        }

        if ($script !== '' && (str_ends_with(strtolower($script), '.phar') || basename($script) === 'yii')) {
            return [\PHP_BINARY, $script];
        }

        return [$script !== '' ? $script : \PHP_BINARY];
    }
}

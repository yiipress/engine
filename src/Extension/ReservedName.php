<?php

declare(strict_types=1);

namespace YiiPress\Extension;

use function preg_match;
use function strtolower;

/**
 * Windows device names that cannot be used as a file or directory name, with or without an extension.
 *
 * @internal
 */
final class ReservedName
{
    public static function isReserved(string $name): bool
    {
        return preg_match('/^(?:con|prn|aux|nul|com\d|lpt\d)$/D', strtolower($name)) === 1;
    }
}

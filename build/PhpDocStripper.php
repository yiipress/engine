<?php

declare(strict_types=1);

namespace YiiPress\Build;

use function is_array;
use function str_replace;
use function str_ends_with;
use function str_starts_with;
use function token_get_all;

use const T_DOC_COMMENT;

final class PhpDocStripper
{
    public static function shouldStrip(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        if (!str_ends_with($path, '.php')) {
            return false;
        }

        return !str_starts_with($path, 'vendor/cebe/markdown/');
    }

    public static function strip(string $code): string
    {
        $tokens = token_get_all($code);
        $result = '';

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if ($token[0] === T_DOC_COMMENT) {
                    continue;
                }

                $result .= $token[1];
                continue;
            }

            $result .= $token;
        }

        return $result;
    }
}

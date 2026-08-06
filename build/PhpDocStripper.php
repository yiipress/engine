<?php

declare(strict_types=1);

namespace YiiPress\Build;

use function is_array;
use function in_array;
use function preg_replace;
use function str_replace;
use function str_ends_with;
use function str_starts_with;
use function str_repeat;
use function substr_count;
use function token_get_all;

use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_WHITESPACE;

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
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    $result .= str_repeat("\n", substr_count($token[1], "\n"));
                    continue;
                }

                if ($token[0] === T_WHITESPACE) {
                    $whitespace = (string) preg_replace('{[ \t]+}', ' ', $token[1]);
                    $whitespace = (string) preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);
                    $result .= (string) preg_replace('{\n +}', "\n", $whitespace);
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

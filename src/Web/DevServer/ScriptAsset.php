<?php

declare(strict_types=1);

namespace YiiPress\Web\DevServer;

use RuntimeException;

final class ScriptAsset
{
    public static function tag(string $fileName): string
    {
        return '<script>' . self::content($fileName) . '</script>';
    }

    private static function content(string $fileName): string
    {
        /** @var array<string, string> $cache */
        static $cache = [];

        return $cache[$fileName] ??= self::read($fileName);
    }

    private static function read(string $fileName): string
    {
        $path = __DIR__ . '/assets/' . $fileName;
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException(sprintf('Unable to read script asset: %s', $path));
        }

        return $content;
    }
}

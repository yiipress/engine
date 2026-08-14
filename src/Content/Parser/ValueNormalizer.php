<?php

declare(strict_types=1);

namespace YiiPress\Content\Parser;

final class ValueNormalizer
{
    public static function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return is_scalar($value) ? (int) $value : $default;
    }

    /** @return list<string> */
    public static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $strings[] = (string) $item;
            }
        }

        return $strings;
    }

    /** @return array<string, mixed> */
    public static function map(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $map[$key] = $item;
            }
        }

        return $map;
    }
}

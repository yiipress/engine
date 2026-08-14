<?php

declare(strict_types=1);

namespace YiiPress\Content\Parser;

use function is_array;
use function is_scalar;

final class ValueNormalizer
{
    public static function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    public static function integer(mixed $value, int $default): int
    {
        return is_scalar($value) ? (int) $value : $default;
    }

    public static function boolean(mixed $value, bool $default): bool
    {
        return is_scalar($value) ? (bool) $value : $default;
    }

    /** @return list<string> */
    public static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $result[] = (string) $item;
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public static function map(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}

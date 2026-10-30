<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

use function array_values;

use const PHP_INT_MAX;

/**
 * Reads the optional `platforms` section of `extension.yaml`.
 *
 * @internal
 */
final class PlatformsParser
{
    private const array OS = ['linux', 'macos', 'windows'];
    private const array ARCH = ['amd64', 'arm64'];

    /**
     * @param array<mixed> $data Top-level mapping.
     */
    public function parse(array $data, FieldReader $fields): ?Platforms
    {
        if (!isset($data['platforms'])) {
            return new Platforms();
        }
        $section = $fields->mapping($data, 'platforms', 'platforms');
        if ($section === null) {
            return null;
        }
        $fields->rejectUnknownKeys($section, ['os', 'arch'], 'platforms');

        return new Platforms(
            $this->values($section, 'os', self::OS, $fields),
            $this->values($section, 'arch', self::ARCH, $fields),
        );
    }

    /**
     * @param array<mixed> $section
     * @param list<string> $allowed
     * @return list<string>
     */
    private function values(array $section, string $key, array $allowed, FieldReader $fields): array
    {
        $field = FieldReader::path('platforms', $key);
        $items = $fields->list($section, $key, $field, 1, PHP_INT_MAX, false);
        if ($items === null) {
            return [];
        }

        $values = [];
        foreach ($fields->strings($items, $field) as $index => $value) {
            if ($fields->oneOf($value, $allowed, FieldReader::path($field, $index)) !== null) {
                $values[$index] = $value;
            }
        }
        $fields->unique($values, $field);

        return array_values($values);
    }
}

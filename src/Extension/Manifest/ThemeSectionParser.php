<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

use function array_map;
use function array_values;
use function in_array;
use function pathinfo;
use function sprintf;
use function strtolower;

use const PATHINFO_EXTENSION;

/**
 * Reads the `theme` section of `extension.yaml`.
 *
 * @internal
 */
final class ThemeSectionParser
{
    private const array KEYS = ['fallback', 'intended_use', 'screenshots', 'demo'];
    private const array SCREENSHOT_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];
    private const int MAX_SCREENSHOTS = 10;
    private const int MAX_INTENDED_USE_LENGTH = 200;

    /**
     * @param array<mixed> $data Top-level mapping.
     */
    public function parse(array $data, FieldReader $fields): ?ThemeMetadata
    {
        if (!isset($data['theme'])) {
            return new ThemeMetadata();
        }
        $section = $fields->mapping($data, 'theme', 'theme');
        if ($section === null) {
            return null;
        }
        $fields->rejectUnknownKeys($section, self::KEYS, 'theme');

        $fallback = $this->fallback($section, $fields);
        $intendedUse = $fields->string($section, 'intended_use', 'theme.intended_use', false);
        $intendedUse = $intendedUse === null
            ? null
            : $fields->text($intendedUse, 'theme.intended_use', self::MAX_INTENDED_USE_LENGTH);
        $demo = $fields->string($section, 'demo', 'theme.demo', false);
        $demo = $demo === null ? null : $fields->httpsUrl($demo, 'theme.demo');

        return new ThemeMetadata(
            fallback: $fallback ?? ThemeFallback::Minimal,
            intendedUse: $intendedUse,
            screenshots: $this->screenshots($section, $fields),
            demo: $demo,
        );
    }

    /**
     * @param array<mixed> $section
     */
    private function fallback(array $section, FieldReader $fields): ?ThemeFallback
    {
        $value = $fields->string($section, 'fallback', 'theme.fallback', false);
        if ($value === null) {
            return null;
        }
        $allowed = array_map(static fn(ThemeFallback $case): string => $case->value, ThemeFallback::cases());
        $value = $fields->oneOf($value, $allowed, 'theme.fallback');

        return $value === null ? null : ThemeFallback::from($value);
    }

    /**
     * @param array<mixed> $section
     * @return list<string>
     */
    private function screenshots(array $section, FieldReader $fields): array
    {
        $field = 'theme.screenshots';
        $items = $fields->list($section, 'screenshots', $field, 0, self::MAX_SCREENSHOTS, false);
        if ($items === null) {
            return [];
        }

        $paths = [];
        foreach ($fields->strings($items, $field) as $index => $path) {
            $itemField = FieldReader::path($field, $index);
            if ($fields->portablePath($path, $itemField) === null) {
                continue;
            }
            if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::SCREENSHOT_EXTENSIONS, true)) {
                $fields->error('path_invalid', $itemField, sprintf('path "%s" must end in .png, .jpg, .jpeg or .webp', $path));
                continue;
            }
            $paths[$index] = $path;
        }
        $fields->unique($paths, $field);

        return array_values($paths);
    }
}

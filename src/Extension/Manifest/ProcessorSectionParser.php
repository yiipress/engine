<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

use function array_map;
use function array_values;
use function sprintf;
use function str_ends_with;
use function strtolower;

use const PHP_INT_MAX;

/**
 * Reads the `processor` section of `extension.yaml`.
 *
 * @internal
 */
final class ProcessorSectionParser
{
    private const int MAX_ENTRY_POINTS = 50;
    private const int MAX_EXAMPLES = 20;

    /**
     * @param array<mixed> $data Top-level mapping.
     */
    public function parse(array $data, FieldReader $fields): ?ProcessorMetadata
    {
        $section = $fields->mapping($data, 'processor', 'processor');
        if ($section === null) {
            return null;
        }
        $fields->rejectUnknownKeys($section, ['entry_points', 'examples'], 'processor');

        return new ProcessorMetadata($this->entryPoints($section, $fields), $this->examples($section, $fields));
    }

    /**
     * @param array<mixed> $section
     * @return list<ProcessorEntryPoint>
     */
    private function entryPoints(array $section, FieldReader $fields): array
    {
        $field = 'processor.entry_points';
        $items = $fields->list($section, 'entry_points', $field, 1, self::MAX_ENTRY_POINTS) ?? [];

        $entryPoints = [];
        $paths = [];
        foreach ($items as $index => $item) {
            $entryPoint = $this->entryPoint($item, FieldReader::path($field, $index), $fields);
            if ($entryPoint !== null) {
                $entryPoints[] = $entryPoint;
                $paths[$index] = $entryPoint->path;
            }
        }
        $fields->unique($paths, $field, ignoreCase: true, itemSuffix: '.path');

        return $entryPoints;
    }

    private function entryPoint(mixed $item, string $field, FieldReader $fields): ?ProcessorEntryPoint
    {
        $map = $fields->asMapping($item, $field);
        if ($map === null) {
            return null;
        }
        $fields->rejectUnknownKeys($map, ['path', 'stages'], $field);

        $pathField = $field . '.path';
        $path = $fields->string($map, 'path', $pathField);
        $path = $path === null ? null : $fields->portablePath($path, $pathField);
        if ($path !== null && !str_ends_with(strtolower($path), '.php')) {
            $fields->error('entry_not_php', $pathField, sprintf('path "%s" must end in .php', $path));
            $path = null;
        }
        $stages = $this->stages($map, $field . '.stages', $fields);

        return $path === null || $stages === null ? null : new ProcessorEntryPoint($path, $stages);
    }

    /**
     * @param array<mixed> $map
     * @return list<ProcessorStage>|null
     */
    private function stages(array $map, string $field, FieldReader $fields): ?array
    {
        $items = $fields->list($map, 'stages', $field, 1, PHP_INT_MAX);
        if ($items === null) {
            return null;
        }

        $allowed = array_map(static fn(ProcessorStage $case): string => $case->value, ProcessorStage::cases());
        $values = [];
        foreach ($fields->strings($items, $field) as $index => $value) {
            if ($fields->oneOf($value, $allowed, FieldReader::path($field, $index)) !== null) {
                $values[$index] = $value;
            }
        }
        $fields->unique($values, $field);

        return array_values(array_map(ProcessorStage::from(...), $values));
    }

    /**
     * @param array<mixed> $section
     * @return list<ProcessorExample>
     */
    private function examples(array $section, FieldReader $fields): array
    {
        $field = 'processor.examples';
        $items = $fields->list($section, 'examples', $field, 0, self::MAX_EXAMPLES, false) ?? [];

        $examples = [];
        foreach ($items as $index => $item) {
            $itemField = FieldReader::path($field, $index);
            $map = $fields->asMapping($item, $itemField);
            if ($map === null) {
                continue;
            }
            $fields->rejectUnknownKeys($map, ['input', 'output'], $itemField);
            $input = $this->catalogPath($map, 'input', $itemField, $fields);
            $output = $this->catalogPath($map, 'output', $itemField, $fields);
            if ($input !== null && $output !== null) {
                $examples[] = new ProcessorExample($input, $output);
            }
        }

        return $examples;
    }

    /**
     * @param array<mixed> $map
     */
    private function catalogPath(array $map, string $key, string $parent, FieldReader $fields): ?string
    {
        $field = FieldReader::path($parent, $key);
        $path = $fields->string($map, $key, $field);

        return $path === null ? null : $fields->portablePath($path, $field);
    }
}

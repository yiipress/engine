<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

use YiiPress\Extension\PortablePath;

use function array_is_list;
use function count;
use function filter_var;
use function get_debug_type;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function mb_strlen;
use function mb_strtolower;
use function parse_url;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function trim;

use const FILTER_VALIDATE_URL;
use const PHP_INT_MAX;
use const PHP_URL_HOST;

/**
 * Reads typed fields from parsed YAML and collects every problem as a {@see ManifestError}.
 *
 * Readers return `null` (or skip an item) when the value is missing or wrong, after recording why.
 *
 * @internal
 */
final class FieldReader
{
    private const int MAX_URL_LENGTH = 2000;

    /** @var list<ManifestError> */
    private array $errors = [];

    /**
     * Builds a field path: `name`, `theme.fallback` or `authors[0]`.
     */
    public static function path(string $parent, string|int $key): string
    {
        if (is_int($key)) {
            return sprintf('%s[%d]', $parent, $key);
        }

        return $parent === '' ? $key : $parent . '.' . $key;
    }

    /**
     * Records an error. The text continues the field path, or «extension.yaml» when the field is empty.
     */
    public function error(string $code, string $field, string $text): void
    {
        $message = $field === '' ? sprintf('extension.yaml %s.', $text) : sprintf('%s: %s.', $field, $text);
        $this->errors[] = new ManifestError($code, $field, $message);
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return list<ManifestError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Reports keys that are not allowed. `$explained` gives a specific text for some keys.
     *
     * @param array<mixed> $map
     * @param list<string> $allowed
     * @param array<string, string> $explained
     */
    public function rejectUnknownKeys(array $map, array $allowed, string $parent, array $explained = []): void
    {
        foreach ($map as $key => $_value) {
            $key = (string) $key;
            if (!in_array($key, $allowed, true)) {
                $this->error('field_unknown', self::path($parent, $key), $explained[$key] ?? 'unknown field; check the spelling');
            }
        }
    }

    /**
     * @param array<mixed> $map
     */
    public function string(array $map, string $key, string $field, bool $required = true): ?string
    {
        $value = $this->present($map, $key, $field, $required);
        if ($value === null || is_string($value)) {
            return $value;
        }

        $this->error('field_type', $field, sprintf('must be a string, got %s', get_debug_type($value)));
        return null;
    }

    /**
     * @param array<mixed> $map
     */
    public function integer(array $map, string $key, string $field): ?int
    {
        $value = $this->present($map, $key, $field, true);
        if ($value === null || is_int($value)) {
            return $value;
        }

        $this->error('field_type', $field, sprintf('must be an integer, got %s', get_debug_type($value)));
        return null;
    }

    /**
     * @param array<mixed> $map
     * @return array<mixed>|null
     */
    public function mapping(array $map, string $key, string $field, bool $required = true): ?array
    {
        $value = $this->present($map, $key, $field, $required);
        return $value === null ? null : $this->asMapping($value, $field);
    }

    /**
     * @return array<mixed>|null
     */
    public function asMapping(mixed $value, string $field): ?array
    {
        if (is_array($value) && ($value === [] || !array_is_list($value))) {
            return $value;
        }

        $this->error('not_mapping', $field, sprintf('must be a mapping, got %s', self::describeType($value)));
        return null;
    }

    /**
     * Reads a list and checks its size.
     *
     * @param array<mixed> $map
     * @return list<mixed>|null
     */
    public function list(array $map, string $key, string $field, int $min, int $max, bool $required = true): ?array
    {
        $value = $this->present($map, $key, $field, $required);
        if ($value === null) {
            return null;
        }
        if (!is_array($value) || !array_is_list($value)) {
            $this->error('field_type', $field, sprintf('must be a list, got %s', self::describeType($value)));
            return null;
        }

        $count = count($value);
        if ($count < $min || $count > $max) {
            $this->error('field_invalid', $field, self::sizeText($min, $max));
            return null;
        }

        return $value;
    }

    /**
     * Keeps string items of a list, reporting the others, with their original indexes.
     *
     * @param list<mixed> $items
     * @return array<int, string>
     */
    public function strings(array $items, string $field): array
    {
        $strings = [];
        foreach ($items as $index => $item) {
            if (is_string($item)) {
                $strings[$index] = $item;
                continue;
            }
            $this->error('field_type', self::path($field, $index), sprintf('must be a string, got %s', get_debug_type($item)));
        }

        return $strings;
    }

    /**
     * Reports items that repeat an earlier one. `$itemSuffix` is appended to each item path, as in `[1].path`.
     *
     * @param array<int, string> $items
     */
    public function unique(array $items, string $field, bool $ignoreCase = false, string $itemSuffix = ''): void
    {
        $seen = [];
        foreach ($items as $index => $item) {
            $key = $ignoreCase ? mb_strtolower($item) : $item;
            if (isset($seen[$key])) {
                $this->error(
                    'field_duplicate',
                    self::path($field, $index) . $itemSuffix,
                    sprintf('"%s" repeats an earlier item%s', $item, $ignoreCase ? ', ignoring case' : ''),
                );
            }
            $seen[$key] = true;
        }
    }

    /**
     * Returns the value when it is one of the allowed ones.
     *
     * @param list<string> $allowed
     */
    public function oneOf(string $value, array $allowed, string $field): ?string
    {
        if (in_array($value, $allowed, true)) {
            return $value;
        }

        $this->error('value_invalid', $field, sprintf('"%s" is not allowed; use one of: %s', $value, implode(', ', $allowed)));
        return null;
    }

    /**
     * Checks free text and returns it trimmed.
     */
    public function text(string $value, string $field, int $maxLength, bool $multiline = false): ?string
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);
        $problem = match (true) {
            $length < 1 || $length > $maxLength => sprintf('must be 1 to %d characters long', $maxLength),
            !$multiline && (str_contains($trimmed, "\n") || str_contains($trimmed, "\r")) => 'must be one line',
            preg_match($multiline ? '/[\x00-\x09\x0B-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $trimmed) === 1
                => 'must not contain control characters',
            default => null,
        };
        if ($problem === null) {
            return $trimmed;
        }

        $this->error('field_invalid', $field, $problem);
        return null;
    }

    public function httpsUrl(string $value, string $field): ?string
    {
        $host = parse_url($value, PHP_URL_HOST);
        if (
            strlen($value) <= self::MAX_URL_LENGTH
            && str_starts_with($value, 'https://')
            && filter_var($value, FILTER_VALIDATE_URL) !== false
            && is_string($host)
            && $host !== ''
        ) {
            return $value;
        }

        $this->error('url_invalid', $field, sprintf('"%s" must be an https:// URL of at most %d characters', $value, self::MAX_URL_LENGTH));
        return null;
    }

    /**
     * Returns the path when it follows the {@see PortablePath} rules.
     */
    public function portablePath(string $value, string $field): ?string
    {
        $problem = PortablePath::problem($value);
        if ($problem === null) {
            return $value;
        }

        $this->error('path_invalid', $field, sprintf('path "%s" %s', $value, $problem));
        return null;
    }

    /**
     * @param array<mixed> $map
     */
    private function present(array $map, string $key, string $field, bool $required): mixed
    {
        $value = $map[$key] ?? null;
        if ($value === null && $required) {
            $this->error('field_missing', $field, 'is required');
        }

        return $value;
    }

    private static function describeType(mixed $value): string
    {
        if (is_array($value)) {
            return array_is_list($value) ? 'a list' : 'a mapping';
        }

        return get_debug_type($value);
    }

    private static function sizeText(int $min, int $max): string
    {
        return match (true) {
            $min === 0 => sprintf('must have at most %d items', $max),
            $min === 1 && $max === PHP_INT_MAX => 'must not be empty',
            default => sprintf('must have %d to %d items', $min, $max),
        };
    }
}

<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

use function array_is_list;
use function count;
use function ini_get;
use function ini_set;
use function is_array;
use function mb_check_encoding;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_replace;
use function strlen;
use function yaml_parse;

/**
 * Parses `extension.yaml` text safely: size and node limits, one document, no PHP object decoding.
 *
 * @internal
 */
final class YamlDocumentLoader
{
    public const int MAX_BYTES = 65536;
    private const int MAX_NODES = 10000;

    /**
     * Returns the top-level mapping, or `null` after recording why there is none.
     *
     * @return array<mixed>|null
     */
    public function load(string $yaml, FieldReader $fields): ?array
    {
        if (strlen($yaml) > self::MAX_BYTES) {
            $fields->error('file_too_large', '', 'is larger than 64 KiB');
            return null;
        }
        if (!mb_check_encoding($yaml, 'UTF-8')) {
            $fields->error('yaml_invalid', '', 'is not valid UTF-8');
            return null;
        }

        $documents = $this->parseDocuments($yaml, $fields);
        if ($documents === null) {
            return null;
        }
        if (count($documents) !== 1) {
            $fields->error('yaml_invalid', '', 'must contain exactly one YAML document');
            return null;
        }

        $remaining = self::MAX_NODES;
        if (!self::withinNodeLimit($documents[0], $remaining)) {
            $fields->error('file_too_large', '', sprintf('has more than %d YAML nodes', self::MAX_NODES));
            return null;
        }

        $root = $documents[0];
        if (!is_array($root) || ($root !== [] && array_is_list($root))) {
            $fields->error('not_mapping', '', 'must be a mapping of fields at the top level');
            return null;
        }

        return $root;
    }

    /**
     * @return list<mixed>|null
     */
    private function parseDocuments(string $yaml, FieldReader $fields): ?array
    {
        $warning = '';
        set_error_handler(static function (int $_level, string $message) use (&$warning): bool {
            $warning = str_replace('yaml_parse(): ', '', $message);
            return true;
        });
        $decodePhp = ini_get('yaml.decode_php');
        ini_set('yaml.decode_php', '0');
        try {
            $documents = yaml_parse($yaml, -1);
        } finally {
            ini_set('yaml.decode_php', $decodePhp === false ? '0' : $decodePhp);
            restore_error_handler();
        }

        if (!is_array($documents)) {
            $fields->error('yaml_invalid', '', $warning === '' ? 'is not valid YAML' : 'is not valid YAML: ' . $warning);
            return null;
        }

        /** @var list<mixed> $documents */
        return $documents;
    }

    private static function withinNodeLimit(mixed $value, int &$remaining): bool
    {
        if (--$remaining < 0) {
            return false;
        }
        if (!is_array($value)) {
            return true;
        }
        // A foreach, not array_all(): an arrow function would capture $remaining by value, so siblings
        // would not share one node budget.
        foreach ($value as $item) {
            if (!self::withinNodeLimit($item, $remaining)) {
                return false;
            }
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace YiiPress\Extension\Archive;

use function ctype_digit;
use function strlen;
use function strpos;
use function substr;

/**
 * Parses the `<length> <key>=<value>\n` records of a pax header.
 *
 * @internal
 */
final class PaxRecords
{
    /**
     * @return array<string, string>
     */
    public static function parse(string $data): array
    {
        $records = [];
        $offset = 0;
        $total = strlen($data);
        while ($offset < $total && $data[$offset] !== "\0") {
            $space = strpos($data, ' ', $offset);
            $digits = $space === false ? '' : substr($data, $offset, $space - $offset);
            if ($space === false || !ctype_digit($digits)) {
                throw TarHeader::invalid('a pax record has no length');
            }
            $length = (int) $digits;
            $record = substr($data, $offset, $length);
            $equals = strpos($record, '=');
            if (
                $length <= strlen($digits) + 1
                || strlen($record) !== $length
                || $record[$length - 1] !== "\n"
                || $equals === false
            ) {
                throw TarHeader::invalid('a pax record is malformed');
            }
            $key = substr($record, strlen($digits) + 1, $equals - strlen($digits) - 1);
            $records[$key] = substr($record, $equals + 1, $length - $equals - 2);
            $offset += $length;
        }

        return $records;
    }
}

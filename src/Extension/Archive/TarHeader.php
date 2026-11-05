<?php

declare(strict_types=1);

namespace YiiPress\Extension\Archive;

use YiiPress\Extension\Install\InstallException;

use function ltrim;
use function octdec;
use function ord;
use function preg_match;
use function rtrim;
use function str_repeat;
use function strlen;
use function substr;
use function substr_replace;

use const PHP_INT_MAX;

/**
 * One 512-byte ustar header.
 *
 * @internal
 */
final readonly class TarHeader
{
    public const int BLOCK = 512;

    private function __construct(
        public string $path,
        public string $type,
        public int $size,
    ) {}

    public static function parse(string $block): self
    {
        if (substr($block, 257, 5) !== 'ustar') {
            throw self::invalid('a header is not in ustar format');
        }
        self::checkChecksum($block);

        $name = rtrim(substr($block, 0, 100), "\0");
        $prefix = rtrim(substr($block, 345, 155), "\0");

        return new self(
            $prefix === '' ? $name : $prefix . '/' . $name,
            $block[156],
            self::number(substr($block, 124, 12), 'size'),
        );
    }

    /**
     * Number of padding bytes after `$size` bytes of entry data.
     */
    public static function padding(int $size): int
    {
        $remainder = $size % self::BLOCK;

        return $remainder === 0 ? 0 : self::BLOCK - $remainder;
    }

    public static function invalid(string $problem): InstallException
    {
        return new InstallException('archive_invalid', "The release archive is broken: $problem.");
    }

    private static function checkChecksum(string $block): void
    {
        $stored = self::number(substr($block, 148, 8), 'checksum');
        $blanked = substr_replace($block, str_repeat(' ', 8), 148, 8);
        $unsigned = 0;
        $signed = 0;
        for ($i = 0; $i < self::BLOCK; $i++) {
            $byte = ord($blanked[$i]);
            $unsigned += $byte;
            $signed += $byte > 127 ? $byte - 256 : $byte;
        }
        if ($stored !== $unsigned && $stored !== $signed) {
            throw self::invalid('a header checksum does not match');
        }
    }

    private static function number(string $field, string $name): int
    {
        if ($field !== '' && (ord($field[0]) & 0x80) !== 0) {
            $value = ord($field[0]) & 0x7F;
            for ($i = 1, $length = strlen($field); $i < $length; $i++) {
                if ($value > (PHP_INT_MAX >> 8)) {
                    throw self::invalid("a header $name is too large");
                }
                $value = ($value << 8) | ord($field[$i]);
            }

            return $value;
        }

        $digits = rtrim(ltrim($field, ' '), " \0");
        if (preg_match('/^[0-7]{1,22}$/D', $digits) !== 1) {
            throw self::invalid("a header $name is not an octal number");
        }

        return (int) octdec($digits);
    }
}

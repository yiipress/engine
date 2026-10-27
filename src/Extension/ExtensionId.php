<?php

declare(strict_types=1);

namespace YiiPress\Extension;

use Stringable;

use function count;
use function explode;
use function preg_match;
use function strlen;

/**
 * Package identifier in the `vendor/name` form.
 */
final readonly class ExtensionId implements Stringable
{
    private const string PART_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    private const int PART_MAX_LENGTH = 64;

    private function __construct(
        public string $vendor,
        public string $name,
    ) {}

    /**
     * Returns `null` when the value is not a valid identifier.
     */
    public static function tryFromString(string $value): ?self
    {
        $parts = explode('/', $value);
        if (count($parts) !== 2 || !self::isValidPart($parts[0]) || !self::isValidPart($parts[1])) {
            return null;
        }

        return new self($parts[0], $parts[1]);
    }

    public function __toString(): string
    {
        return $this->vendor . '/' . $this->name;
    }

    private static function isValidPart(string $part): bool
    {
        return strlen($part) <= self::PART_MAX_LENGTH
            && preg_match(self::PART_PATTERN, $part) === 1
            && !ReservedName::isReserved($part);
    }
}

<?php

declare(strict_types=1);

namespace YiiPress\Extension;

use function php_uname;
use function strtolower;

use const PHP_OS_FAMILY;

/**
 * Operating system and CPU architecture of the running YiiPress, in the words used by `platforms` in
 * `extension.yaml`. A value is `null` when it is not one of those words.
 */
final readonly class Platform
{
    public function __construct(
        public ?string $os,
        public ?string $arch,
    ) {}

    public static function current(): self
    {
        return self::fromPhp(PHP_OS_FAMILY, php_uname('m'));
    }

    /**
     * @param string $osFamily Value of `PHP_OS_FAMILY`.
     * @param string $machine Value of `php_uname('m')`.
     */
    public static function fromPhp(string $osFamily, string $machine): self
    {
        $os = match ($osFamily) {
            'Linux' => 'linux',
            'Darwin' => 'macos',
            'Windows' => 'windows',
            default => null,
        };
        $arch = match (strtolower($machine)) {
            'x86_64', 'amd64' => 'amd64',
            'aarch64', 'arm64' => 'arm64',
            default => null,
        };

        return new self($os, $arch);
    }
}

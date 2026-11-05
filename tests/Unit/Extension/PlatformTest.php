<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\Platform;

use function PHPUnit\Framework\assertSame;

final class PlatformTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, ?string, ?string}>
     */
    public static function phpValuesDataProvider(): iterable
    {
        yield 'linux amd64' => ['Linux', 'x86_64', 'linux', 'amd64'];
        yield 'macos arm64' => ['Darwin', 'arm64', 'macos', 'arm64'];
        yield 'windows amd64' => ['Windows', 'AMD64', 'windows', 'amd64'];
        yield 'linux aarch64' => ['Linux', 'aarch64', 'linux', 'arm64'];
        yield 'unknown' => ['BSD', 'riscv64', null, null];
    }

    #[DataProvider('phpValuesDataProvider')]
    public function testMapsPhpValues(string $osFamily, string $machine, ?string $os, ?string $arch): void
    {
        $platform = Platform::fromPhp($osFamily, $machine);

        assertSame($os, $platform->os);
        assertSame($arch, $platform->arch);
    }

    public function testCurrentMatchesRunningPhp(): void
    {
        $expected = Platform::fromPhp(PHP_OS_FAMILY, php_uname('m'));

        assertSame($expected->os, Platform::current()->os);
        assertSame($expected->arch, Platform::current()->arch);
    }
}

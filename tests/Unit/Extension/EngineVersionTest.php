<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\EngineVersion;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

final class EngineVersionTest extends TestCase
{
    private const string COMMIT = '80964110a7e8b1c2d3e4f5a6b7c8d9e0f1a2b3c4';

    public function testReleaseUsesPackageVersion(): void
    {
        $version = EngineVersion::fromBuild('0.1.13');

        assertSame('0.1.13', $version->version);
        assertNull($version->commit);
        assertFalse($version->isNightly());
        assertSame('0.1.13', $version->describe());
    }

    public function testReleaseAcceptsPrerelease(): void
    {
        assertSame('0.2.0-rc.1', EngineVersion::fromBuild('0.2.0-rc.1')->version);
    }

    public function testNightlyUsesBaseRelease(): void
    {
        $version = EngineVersion::fromBuild('dev-master', self::COMMIT, '0.1.13');

        assertSame('0.1.13', $version->version);
        assertSame(self::COMMIT, $version->commit);
        assertTrue($version->isNightly());
        assertSame('nightly built on 0.1.13 (commit 80964110a7e8)', $version->describe());
    }

    public function testNightlyWithoutBaseReleaseHasUnknownVersion(): void
    {
        $version = EngineVersion::fromBuild(null, self::COMMIT);

        assertNull($version->version);
        assertTrue($version->isNightly());
        assertSame('a nightly build of unknown version (commit 80964110a7e8)', $version->describe());
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function unknownVersionDataProvider(): iterable
    {
        yield 'no package' => [null];
        yield 'branch' => ['dev-master'];
        yield 'dev suffix' => ['0.2.x-dev'];
        yield 'no version set' => ['1.0.0+no-version-set'];
        yield 'commit reference' => [self::COMMIT];
        yield 'v prefix' => ['v0.1.13'];
    }

    #[DataProvider('unknownVersionDataProvider')]
    public function testSourceCheckoutHasUnknownVersion(?string $packageVersion): void
    {
        $version = EngineVersion::fromBuild($packageVersion);

        assertNull($version->version);
        assertNull($version->commit);
        assertSame('an unknown version', $version->describe());
    }

    public function testCurrentSourceCheckoutIsNotNightly(): void
    {
        assertFalse(EngineVersion::current()->isNightly());
    }
}

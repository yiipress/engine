<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Install;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\Install\StagedPackage;
use YiiPress\Extension\Manifest\ManifestParser;
use YiiPress\Extension\Manifest\ThemeFallback;
use YiiPress\Extension\Release\DownloadedArchive;
use YiiPress\Extension\Release\Release;
use YiiPress\Extension\Release\SelectedRelease;
use YiiPress\Tests\Unit\Extension\Release\ReleaseSelectorTest;

use function str_repeat;

final class StagedPackageTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ThemeFallback}>
     */
    public static function fallbacks(): iterable
    {
        yield 'default' => ['', ThemeFallback::Minimal];
        yield 'minimal' => ["theme:\n  fallback: minimal\n", ThemeFallback::Minimal];
        yield 'none' => ["theme:\n  fallback: none\n", ThemeFallback::None];
    }

    #[DataProvider('fallbacks')]
    public function testLockEntryKeepsThemeFallbackOfManifest(string $themeSection, ThemeFallback $expected): void
    {
        $locked = self::staged(ReleaseSelectorTest::manifest('1.0.0') . "\n" . $themeSection)->locked('themes/wonder', 'wonder');

        self::assertSame($expected, $locked->fallback);
    }

    private static function staged(string $yaml): StagedPackage
    {
        $manifest = new ManifestParser()->parse($yaml);

        return new StagedPackage(
            manifest: $manifest,
            selected: new SelectedRelease(new Release('v1.0.0', '1.0.0'), $manifest, 'newest'),
            source: 'https://github.com/yiipress/theme-wonder',
            archive: new DownloadedArchive('/tmp/archive.tar.gz', 'https://codeload.github.com/x', str_repeat('a', 64), 1),
            commit: str_repeat('b', 40),
            manifestSha256: str_repeat('c', 64),
            path: '.yiipress/staging/x/root/package',
            files: [],
        );
    }
}

<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Content\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use YiiPress\Content\Parser\SiteIconFinder;

use function PHPUnit\Framework\assertSame;

final class SiteIconFinderTest extends TestCase
{
    private string $contentDir;

    protected function setUp(): void
    {
        $this->contentDir = $this->createTempDirectory();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->contentDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->contentDir);
    }

    #[DataProvider('supportedIconProvider')]
    public function testFindsSupportedIconFormatsWithMimeTypes(string $filename, string $type): void
    {
        file_put_contents($this->contentDir . '/' . $filename, 'icon');

        $icons = (new SiteIconFinder())->find($this->contentDir);

        assertSame([$filename], array_column($icons, 'path'));
        assertSame([$type], array_column($icons, 'type'));
    }

    public function testIgnoresNonConventionalFilename(): void
    {
        file_put_contents($this->contentDir . '/favicon.ico', 'ignored');

        assertSame([], (new SiteIconFinder())->find($this->contentDir));
    }

    public function testFindsMultipleSupportedIcons(): void
    {
        file_put_contents($this->contentDir . '/icon.svg', '<svg/>');
        file_put_contents($this->contentDir . '/icon.png', 'png');

        $icons = (new SiteIconFinder())->find($this->contentDir);

        assertSame(['icon.svg', 'icon.png'], array_column($icons, 'path'));
    }

    public function testReturnsNoIconsWhenContentDirectoryHasNone(): void
    {
        assertSame([], (new SiteIconFinder())->find($this->contentDir));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function supportedIconProvider(): iterable
    {
        yield 'SVG' => ['icon.svg', 'image/svg+xml'];
        yield 'ICO' => ['icon.ico', 'image/x-icon'];
        yield 'PNG' => ['icon.png', 'image/png'];
        yield 'GIF' => ['icon.gif', 'image/gif'];
        yield 'WebP' => ['icon.webp', 'image/webp'];
        yield 'AVIF' => ['icon.avif', 'image/avif'];
        yield 'JPG' => ['icon.jpg', 'image/jpeg'];
        yield 'JPEG' => ['icon.jpeg', 'image/jpeg'];
    }

    private function createTempDirectory(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $path = sys_get_temp_dir() . '/yiipress-icon-test-' . bin2hex(random_bytes(16));
            if (@mkdir($path, 0o700)) {
                return $path;
            }
        }

        throw new RuntimeException('Could not create test temp directory.');
    }
}

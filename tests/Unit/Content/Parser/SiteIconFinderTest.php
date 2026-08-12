<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Content\Parser;

use PHPUnit\Framework\TestCase;
use YiiPress\Content\Parser\SiteIconFinder;

use function PHPUnit\Framework\assertSame;

final class SiteIconFinderTest extends TestCase
{
    private string $contentDir;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/yiipress-icon-test-' . uniqid();
        mkdir($this->contentDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->contentDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->contentDir);
    }

    public function testFindsSupportedIconFormatsWithMimeTypes(): void
    {
        file_put_contents($this->contentDir . '/icon.svg', '<svg/>');
        file_put_contents($this->contentDir . '/icon.png', 'png');
        file_put_contents($this->contentDir . '/favicon.ico', 'ignored');

        $icons = (new SiteIconFinder())->find($this->contentDir);

        assertSame(['icon.svg', 'icon.png'], array_column($icons, 'path'));
        assertSame(['image/svg+xml', 'image/png'], array_column($icons, 'type'));
    }

    public function testReturnsNoIconsWhenContentDirectoryHasNone(): void
    {
        assertSame([], (new SiteIconFinder())->find($this->contentDir));
    }
}

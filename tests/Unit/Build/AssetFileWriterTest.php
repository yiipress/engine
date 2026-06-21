<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\AssetFileWriter;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertStringEqualsFile;
use function PHPUnit\Framework\assertTrue;

final class AssetFileWriterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-asset-writer-test-' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    public function testWritesMinifiedSupportedAssets(): void
    {
        $source = $this->tempDir . '/style.css';
        $target = $this->tempDir . '/style.out.css';
        file_put_contents($source, 'body { color: red; }');

        $writer = new AssetFileWriter();

        assertTrue($writer->writeIfChanged($source, $target, minify: true));
        assertStringEqualsFile($target, 'body{color:red}');
        assertFalse($writer->writeIfChanged($source, $target, minify: true));
    }

    public function testCopiesUnsupportedOrUnminifiedAssetsUnchanged(): void
    {
        $source = $this->tempDir . '/logo.svg';
        $target = $this->tempDir . '/logo.out.svg';
        file_put_contents($source, '<svg>  </svg>');

        $writer = new AssetFileWriter();

        assertTrue($writer->writeIfChanged($source, $target, minify: true));
        assertStringEqualsFile($target, '<svg>  </svg>');

        file_put_contents($source, '<svg></svg>');

        assertTrue($writer->writeIfChanged($source, $target, minify: false));
        assertStringEqualsFile($target, '<svg></svg>');
    }
}

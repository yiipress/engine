<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\FileCopy;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringEqualsFile;
use function PHPUnit\Framework\assertTrue;

final class FileCopyTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-file-copy-test-' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    public function testCopyIfChangedSkipsDestinationWithMatchingMtimeAndSize(): void
    {
        $source = $this->tempDir . '/source.css';
        $target = $this->tempDir . '/target.css';
        file_put_contents($source, 'body{}');
        touch($source, 1_700_000_000);

        assertTrue(FileCopy::copyIfChanged($source, $target));
        assertStringEqualsFile($target, 'body{}');
        assertSame(filemtime($source), filemtime($target));

        assertFalse(FileCopy::copyIfChanged($source, $target));
    }
}

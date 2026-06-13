<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\FileWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function PHPUnit\Framework\assertSame;

final class FileWriterTest extends TestCase
{
    public function testWriteCreatesFileWithContents(): void
    {
        $file = sys_get_temp_dir() . '/yiipress-file-writer-' . uniqid() . '.txt';

        try {
            FileWriter::write($file, 'contents');

            assertSame('contents', file_get_contents($file));
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testWriteThrowsWhenTargetCannotBeWritten(): void
    {
        $directory = sys_get_temp_dir() . '/yiipress-file-writer-' . uniqid();
        mkdir($directory, 0o755);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unable to write file');

            FileWriter::write($directory, 'contents');
        } finally {
            rmdir($directory);
        }
    }
}

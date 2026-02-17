<?php

declare(strict_types=1);

namespace App\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertDirectoryDoesNotExist;
use function PHPUnit\Framework\assertDirectoryExists;
use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class CleanCommandTest extends TestCase
{
    private string $outputDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->outputDir = dirname(__DIR__, 2) . '/Support/Data/output';
        $this->cacheDir = dirname(__DIR__, 3) . '/runtime/cache/build';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->outputDir);
        $this->removeDir($this->cacheDir);
    }

    public function testCleanRemovesOutputDirectory(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' --no-cache'
            . ' 2>&1',
            $buildOutput,
            $buildExitCode,
        );

        assertSame(0, $buildExitCode);
        assertDirectoryExists($this->outputDir);

        exec(
            $yii . ' clean'
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $cleanOutput,
            $cleanExitCode,
        );

        assertSame(0, $cleanExitCode);
        assertDirectoryDoesNotExist($this->outputDir);
        assertStringContainsString('Removed output directory', implode("\n", $cleanOutput));
    }

    public function testCleanRemovesCacheDirectory(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $buildOutput,
            $buildExitCode,
        );

        assertSame(0, $buildExitCode);
        assertDirectoryExists($this->cacheDir);

        exec(
            $yii . ' clean'
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $cleanOutput,
            $cleanExitCode,
        );

        assertSame(0, $cleanExitCode);
        assertDirectoryDoesNotExist($this->cacheDir);
        assertStringContainsString('Removed cache directory', implode("\n", $cleanOutput));
    }

    public function testCleanHandlesMissingDirectories(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $nonexistentDir = $this->outputDir . '/nonexistent';

        exec(
            $yii . ' clean'
            . ' --output-dir=' . escapeshellarg($nonexistentDir)
            . ' 2>&1',
            $output,
            $exitCode,
        );

        $outputText = implode("\n", $output);

        assertSame(0, $exitCode);
        assertStringContainsString('Output directory not found', $outputText);
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}

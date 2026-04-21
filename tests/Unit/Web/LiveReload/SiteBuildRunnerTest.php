<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web\LiveReload;

use App\Web\LiveReload\SiteBuildRunner;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;
use function chmod;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;
use function uniqid;

final class SiteBuildRunnerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress_site_build_runner_test_' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testBuildUsesIncrementalCommandLine(): void
    {
        $recordFile = $this->tempDir . '/command.log';
        $script = $this->tempDir . '/fake-yii';
        file_put_contents(
            $script,
            "#!/bin/sh\nprintf '%s\n' \"\$*\" > " . escapeshellarg($recordFile) . "\nexit 0\n",
        );
        chmod($script, 0o755);

        $runner = new SiteBuildRunner($script, $this->tempDir . '/content', $this->tempDir . '/output');

        self::assertTrue($runner->build());

        $commandLine = file_get_contents($recordFile);
        assertSame(
            "build --content-dir={$this->tempDir}/content --output-dir={$this->tempDir}/output\n",
            $commandLine,
        );
        assertStringContainsString('--content-dir=', $commandLine);
        assertStringContainsString('--output-dir=', $commandLine);
        assertStringNotContainsString('--no-cache', $commandLine);
    }

    public function testBuildReturnsFalseWhenCommandFails(): void
    {
        $script = $this->tempDir . '/fail-yii';
        file_put_contents($script, "#!/bin/sh\nexit 1\n");
        chmod($script, 0o755);

        $runner = new SiteBuildRunner($script, $this->tempDir . '/content', $this->tempDir . '/output');

        assertFalse($runner->build());
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}

<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Yiisoft\Yii\Console\ExitCode;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class CheckCommandTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/yiipress-check-command-test-' . uniqid();
        mkdir($this->outputDir . '/docs', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->outputDir);
    }

    public function testCheckPassesForValidGeneratedSite(): void
    {
        file_put_contents($this->outputDir . '/index.html', '<a href="./docs/#intro">Docs</a>');
        file_put_contents($this->outputDir . '/docs/index.html', '<h1 id="intro">Intro</h1>');

        $result = $this->runCheck();

        assertSame(ExitCode::OK, $result['exitCode'], $result['output']);
        assertStringContainsString('Site check passed.', $result['output']);
    }

    public function testCheckFailsForBrokenGeneratedSiteLink(): void
    {
        file_put_contents($this->outputDir . '/index.html', '<a href="./missing/">Missing</a>');

        $result = $this->runCheck();

        assertSame(ExitCode::DATAERR, $result['exitCode'], $result['output']);
        assertStringContainsString('index.html: local target not found "./missing/"', $result['output']);
        assertStringContainsString('Site check failed: 1 issue(s).', $result['output']);
    }

    public function testCheckFailsForMissingOutputDirectory(): void
    {
        $this->removeDir($this->outputDir);

        $result = $this->runCheck();

        assertSame(ExitCode::DATAERR, $result['exitCode'], $result['output']);
        assertStringContainsString('Output directory not found', $result['output']);
    }

    /**
     * @return array{exitCode: int, output: string}
     */
    private function runCheck(): array
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        exec(
            $yii . ' check:links'
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $output,
            $exitCode,
        );

        return ['exitCode' => $exitCode, 'output' => implode("\n", $output)];
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

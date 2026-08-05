<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Tester\CommandTester;
use YiiPress\Console\PluginInitCommand;
use YiiPress\Content\Model\ProcessorConfig;
use YiiPress\Processor\ProjectProcessorLoader;
use Yiisoft\Yii\Console\ExitCode;

use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;
use function uniqid;

final class PluginInitCommandTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        $this->rootPath = sys_get_temp_dir() . '/yiipress-plugin-init-command-' . uniqid();
        mkdir($this->rootPath . '/content', 0o755, true);
        file_put_contents($this->rootPath . '/content/config.yaml', "title: Test\nlanguages: [en]\n");
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->rootPath);
    }

    #[Test]
    public function createsLoadableProjectProcessor(): void
    {
        $tester = new CommandTester(new PluginInitCommand($this->rootPath));

        $exitCode = $tester->execute(['name' => 'Badge Labels']);
        $filePath = $this->rootPath . '/content/processors/badge-labels.php';

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertFileExists($filePath);
        self::assertStringContainsString('Created:', $tester->getDisplay());

        $processors = new ProjectProcessorLoader(
            $this->rootPath . '/content',
            $this->rootPath . '/content/config.yaml',
        )->load(new ProcessorConfig());
        self::assertCount(1, $processors->contentBeforeMarkdown);
    }

    #[Test]
    public function supportsCustomContentDirectory(): void
    {
        mkdir($this->rootPath . '/site-content');
        $tester = new CommandTester(new PluginInitCommand($this->rootPath));

        $exitCode = $tester->execute(['name' => 'Badge', '--content-dir' => 'site-content']);

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertFileExists($this->rootPath . '/site-content/processors/badge.php');
    }

    #[Test]
    public function doesNotOverwriteExistingPlugin(): void
    {
        mkdir($this->rootPath . '/content/processors');
        $filePath = $this->rootPath . '/content/processors/badge.php';
        file_put_contents($filePath, 'existing');
        $tester = new CommandTester(new PluginInitCommand($this->rootPath));

        $exitCode = $tester->execute(['name' => 'Badge']);

        self::assertSame(ExitCode::DATAERR, $exitCode);
        self::assertSame('existing', file_get_contents($filePath));
    }

    #[Test]
    public function rejectsMissingContentDirectory(): void
    {
        $tester = new CommandTester(new PluginInitCommand($this->rootPath));

        $exitCode = $tester->execute(['name' => 'Badge', '--content-dir' => 'missing']);

        self::assertSame(ExitCode::DATAERR, $exitCode);
        self::assertStringContainsString('Content directory not found', $tester->getDisplay());
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

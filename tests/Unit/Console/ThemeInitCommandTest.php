<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Tester\CommandTester;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Console\ThemeInitCommand;
use Yiisoft\Yii\Console\ExitCode;

use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function PHPUnit\Framework\assertSame;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;
use function uniqid;

final class ThemeInitCommandTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/yiipress-theme-init-command-test-' . uniqid();
        mkdir($this->rootDir . '/source', 0o755, true);
        mkdir($this->rootDir . '/content');
        file_put_contents($this->rootDir . '/source/entry.php', 'entry');
        file_put_contents($this->rootDir . '/content/config.yaml', "title: Test\nlanguages: [en]\n");
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->rootDir);
    }

    public function testInitializesMinimalThemeIntoDefaultCustomDirectory(): void
    {
        $tester = new CommandTester(new ThemeInitCommand($this->rootDir, $this->registry()));

        $exitCode = $tester->execute([]);

        assertSame(ExitCode::OK, $exitCode);
        assertSame('entry', file_get_contents($this->rootDir . '/themes/custom/entry.php'));
        assertSame("title: Test\nlanguages: [en]\ntheme: \"custom\"\n", file_get_contents($this->rootDir . '/content/config.yaml'));
        self::assertStringContainsString('themes/custom', $tester->getDisplay());
        self::assertStringContainsString('theme custom', $tester->getDisplay());
    }

    public function testInitializesThemeIntoCustomDirectory(): void
    {
        $tester = new CommandTester(new ThemeInitCommand($this->rootDir, $this->registry()));

        $exitCode = $tester->execute(['target-dir' => 'themes/brand']);

        assertSame(ExitCode::OK, $exitCode);
        assertSame('entry', file_get_contents($this->rootDir . '/themes/brand/entry.php'));
        assertSame("title: Test\nlanguages: [en]\ntheme: \"brand\"\n", file_get_contents($this->rootDir . '/content/config.yaml'));
    }

    public function testInitializesThemeIntoAbsoluteDirectory(): void
    {
        $absoluteTarget = $this->rootDir . '/abs-target';
        $tester = new CommandTester(new ThemeInitCommand($this->rootDir, $this->registry()));

        $exitCode = $tester->execute(['target-dir' => $absoluteTarget]);

        assertSame(ExitCode::OK, $exitCode);
        assertSame('entry', file_get_contents($absoluteTarget . '/entry.php'));
        assertSame("title: Test\nlanguages: [en]\ntheme: \"abs-target\"\n", file_get_contents($this->rootDir . '/content/config.yaml'));
    }

    public function testReplacesExistingThemeInContentConfig(): void
    {
        file_put_contents($this->rootDir . '/content/config.yaml', "title: Test\ntheme: minimal\nlanguages: [en]\n");
        $tester = new CommandTester(new ThemeInitCommand($this->rootDir, $this->registry()));

        $exitCode = $tester->execute([]);

        assertSame(ExitCode::OK, $exitCode);
        assertSame("title: Test\ntheme: \"custom\"\nlanguages: [en]\n", file_get_contents($this->rootDir . '/content/config.yaml'));
    }

    public function testConfiguresCustomContentDirectory(): void
    {
        mkdir($this->rootDir . '/site-content');
        file_put_contents($this->rootDir . '/site-content/config.yaml', "title: Site\n");
        $tester = new CommandTester(new ThemeInitCommand($this->rootDir, $this->registry()));

        $exitCode = $tester->execute(['--content-dir' => 'site-content']);

        assertSame(ExitCode::OK, $exitCode);
        assertSame("title: Site\ntheme: \"custom\"\n", file_get_contents($this->rootDir . '/site-content/config.yaml'));
        assertSame("title: Test\nlanguages: [en]\n", file_get_contents($this->rootDir . '/content/config.yaml'));
    }

    public function testDoesNotCopyFilesWhenContentConfigIsMissing(): void
    {
        unlink($this->rootDir . '/content/config.yaml');
        $tester = new CommandTester(new ThemeInitCommand($this->rootDir, $this->registry()));

        $exitCode = $tester->execute([]);

        assertSame(ExitCode::DATAERR, $exitCode);
        self::assertFileDoesNotExist($this->rootDir . '/themes/custom/entry.php');
        self::assertStringContainsString('Content config file', $tester->getDisplay());
    }

    public function testDoesNotCopyFilesWhenTargetThemeNameIsInvalid(): void
    {
        $tester = new CommandTester(new ThemeInitCommand($this->rootDir, $this->registry()));

        $exitCode = $tester->execute(['target-dir' => 'themes/.custom']);

        assertSame(ExitCode::DATAERR, $exitCode);
        self::assertFileDoesNotExist($this->rootDir . '/themes/.custom/entry.php');
        self::assertStringContainsString('is not a valid project theme name', $tester->getDisplay());
    }

    public function testReturnsDataErrorForUnknownTheme(): void
    {
        $tester = new CommandTester(new ThemeInitCommand($this->rootDir, $this->registry()));

        $exitCode = $tester->execute(['--theme' => 'missing']);

        assertSame(ExitCode::DATAERR, $exitCode);
        self::assertStringContainsString('Theme "missing" is not registered.', $tester->getDisplay());
    }

    private function registry(): ThemeRegistry
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', $this->rootDir . '/source'));

        return $registry;
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

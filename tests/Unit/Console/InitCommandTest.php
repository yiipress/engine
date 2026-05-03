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
use YiiPress\Console\InitCommand;
use YiiPress\Content\Parser\CollectionConfigParser;
use YiiPress\Content\Parser\NavigationParser;
use YiiPress\Content\Parser\SiteConfigParser;
use Yiisoft\Yii\Console\ExitCode;

use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;
use function uniqid;

final class InitCommandTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        $this->rootPath = sys_get_temp_dir() . '/yiipress-init-command-' . uniqid();
        mkdir($this->rootPath);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->rootPath);
    }

    #[Test]
    public function initCreatesDefaultContentStructure(): void
    {
        $tester = new CommandTester(new InitCommand($this->rootPath));

        $exitCode = $tester->execute([]);
        $contentDir = $this->rootPath . '/content';

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertFileExists($contentDir . '/config.yaml');
        self::assertFileExists($contentDir . '/navigation.yaml');
        self::assertFileExists($contentDir . '/page/_collection.yaml');
        self::assertFileExists($contentDir . '/blog/_collection.yaml');
        self::assertStringContainsString('Created:', $tester->getDisplay());

        $siteConfig = new SiteConfigParser()->parse($contentDir . '/config.yaml');
        self::assertSame('My YiiPress Site', $siteConfig->title);
        self::assertSame(['en'], $siteConfig->i18n->languages);

        $navigation = new NavigationParser()->parse($contentDir . '/navigation.yaml');
        self::assertContains('main', $navigation->menuNames());
        self::assertCount(2, $navigation->menu('main'));

        $pageCollection = new CollectionConfigParser()->parse($contentDir . '/page/_collection.yaml', 'page');
        self::assertSame('page', $pageCollection->name);
        self::assertSame('/:slug/', $pageCollection->permalink);
        self::assertFalse($pageCollection->feed);

        $blogCollection = new CollectionConfigParser()->parse($contentDir . '/blog/_collection.yaml', 'blog');
        self::assertSame('blog', $blogCollection->name);
        self::assertSame('/blog/:slug/', $blogCollection->permalink);
        self::assertTrue($blogCollection->feed);
    }

    #[Test]
    public function initCreatesCustomContentDirectory(): void
    {
        $tester = new CommandTester(new InitCommand($this->rootPath));

        $exitCode = $tester->execute(['--content-dir' => 'site-content']);

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertFileExists($this->rootPath . '/site-content/config.yaml');
        self::assertFileExists($this->rootPath . '/site-content/blog/_collection.yaml');
        self::assertFileDoesNotExist($this->rootPath . '/content/config.yaml');
    }

    #[Test]
    public function initDoesNotOverwriteExistingFiles(): void
    {
        mkdir($this->rootPath . '/content', 0o755, true);
        file_put_contents($this->rootPath . '/content/config.yaml', 'title: Existing');

        $tester = new CommandTester(new InitCommand($this->rootPath));

        $exitCode = $tester->execute([]);

        self::assertSame(ExitCode::DATAERR, $exitCode);
        self::assertStringContainsString('Path already exists:', $tester->getDisplay());
        self::assertSame('title: Existing', file_get_contents($this->rootPath . '/content/config.yaml'));
        self::assertFileDoesNotExist($this->rootPath . '/content/navigation.yaml');
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

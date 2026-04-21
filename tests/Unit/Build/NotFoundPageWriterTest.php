<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\NotFoundPageWriter;
use App\Build\Theme;
use App\Build\ThemeRegistry;
use App\Build\TemplateResolver;
use App\Content\Model\SiteConfig;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class NotFoundPageWriterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-404-test-' . uniqid();
        mkdir($this->tempDir . '/output', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testWrites404PageWithRelativeAssetPaths(): void
    {
        $writer = new NotFoundPageWriter($this->createTemplateResolver());
        $writer->write($this->createSiteConfig(), $this->tempDir . '/output');

        $filePath = $this->tempDir . '/output/404.html';
        assertFileExists($filePath);

        $html = (string) file_get_contents($filePath);
        assertStringContainsString('href="./assets/theme/style.css"', $html);
        assertStringContainsString('src="./assets/theme/dark-mode.js"', $html);
        assertStringContainsString('src="./assets/theme/ui-language.js"', $html);
        assertStringContainsString('href="./" data-ui-key="go_to_home_page">Go to home page</a>', $html);
        assertStringNotContainsString('href="/assets/theme/style.css"', $html);
        assertStringNotContainsString('src="/assets/theme/dark-mode.js"', $html);
    }

    private function createTemplateResolver(): TemplateResolver
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));

        return new TemplateResolver($registry);
    }

    private function createSiteConfig(): SiteConfig
    {
        return new SiteConfig(
            title: 'Test Site',
            description: '',
            baseUrl: 'https://example.com',
            defaultLanguage: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
        );
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

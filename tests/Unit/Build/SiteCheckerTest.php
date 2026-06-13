<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Build\SiteChecker;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;

final class SiteCheckerTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/yiipress-site-checker-test-' . uniqid();
        mkdir($this->outputDir . '/blog', 0o755, true);
        mkdir($this->outputDir . '/assets', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->outputDir);
    }

    public function testPassesForExistingLocalTargetsAndFragments(): void
    {
        file_put_contents($this->outputDir . '/index.html', '<a href="./blog/#post">Post</a><img src="/assets/logo.svg"><a href="mailto:test@example.com">Mail</a>');
        file_put_contents($this->outputDir . '/blog/index.html', '<h2 id="post">Post</h2>');
        file_put_contents($this->outputDir . '/assets/logo.svg', '<svg/>');

        $issues = (new SiteChecker())->check($this->outputDir);

        assertSame([], $issues);
    }

    public function testExtractsCaseInsensitiveLinkAttributes(): void
    {
        file_put_contents($this->outputDir . '/index.html', '<A HREF="./blog/#post">Post</A><IMG SRC="/assets/logo.svg">');
        file_put_contents($this->outputDir . '/blog/index.html', '<h2 ID="post">Post</h2>');
        file_put_contents($this->outputDir . '/assets/logo.svg', '<svg/>');

        $issues = (new SiteChecker())->check($this->outputDir);

        assertSame([], $issues);
    }

    public function testReportsMissingLocalTargetsAndFragments(): void
    {
        file_put_contents($this->outputDir . '/index.html', '<a href="./missing/">Missing</a><a href="./blog/#missing">Bad fragment</a>');
        file_put_contents($this->outputDir . '/blog/index.html', '<h2 id="post">Post</h2>');

        $issues = (new SiteChecker())->check($this->outputDir);

        assertCount(2, $issues);
        assertSame('local target not found', $issues[0]->message);
        assertSame('./missing/', $issues[0]->target);
        assertSame('fragment not found', $issues[1]->message);
        assertSame('./blog/#missing', $issues[1]->target);
    }

    public function testChecksExternalLinksOnlyWhenRequested(): void
    {
        file_put_contents($this->outputDir . '/index.html', '<a href="https://example.test/broken">Broken</a>');
        $checker = new SiteChecker(static fn (string $url): bool => $url !== 'https://example.test/broken');

        assertSame([], $checker->check($this->outputDir));

        $issues = $checker->check($this->outputDir, checkExternal: true);

        assertCount(1, $issues);
        assertSame('external link is not reachable', $issues[0]->message);
        assertSame('https://example.test/broken', $issues[0]->target);
    }

    public function testRejectsLinksEscapingOutputDirectory(): void
    {
        file_put_contents($this->outputDir . '/blog/index.html', '<a href="../../secret.html">Secret</a>');

        $issues = (new SiteChecker())->check($this->outputDir);

        assertCount(1, $issues);
        assertSame('local target not found', $issues[0]->message);
        assertSame('../../secret.html', $issues[0]->target);
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

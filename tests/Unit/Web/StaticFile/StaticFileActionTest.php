<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web\StaticFile;

use App\Web\LiveReload\SiteBuildRunner;
use App\Web\StaticFile\StaticFileAction;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequest;
use HttpSoft\Message\StreamFactory;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class StaticFileActionTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress_static_test_' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testServesIndexHtmlForDirectoryPath(): void
    {
        mkdir($this->tempDir . '/blog', 0o755, true);
        file_put_contents($this->tempDir . '/blog/index.html', '<h1>Blog</h1>');

        $action = $this->createAction();
        $response = $action(new ServerRequest(uri: '/blog/'));

        assertSame(200, $response->getStatusCode());
        assertSame('<h1>Blog</h1>', (string) $response->getBody());
        assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
    }

    public function testServesRootIndexHtml(): void
    {
        file_put_contents($this->tempDir . '/index.html', '<h1>Home</h1>');

        $action = $this->createAction();
        $response = $action(new ServerRequest(uri: '/'));

        assertSame(200, $response->getStatusCode());
        assertSame('<h1>Home</h1>', (string) $response->getBody());
    }

    public function testServesStaticFileDirectly(): void
    {
        file_put_contents($this->tempDir . '/style.css', 'body { color: red; }');

        $action = $this->createAction();
        $response = $action(new ServerRequest(uri: '/style.css'));

        assertSame(200, $response->getStatusCode());
        assertSame('body { color: red; }', (string) $response->getBody());
        assertStringContainsString('text/css', $response->getHeaderLine('Content-Type'));
    }

    public function testServesXmlFiles(): void
    {
        file_put_contents($this->tempDir . '/sitemap.xml', '<?xml version="1.0"?>');

        $action = $this->createAction();
        $response = $action(new ServerRequest(uri: '/sitemap.xml'));

        assertSame(200, $response->getStatusCode());
        assertStringContainsString('application/xml', $response->getHeaderLine('Content-Type'));
    }

    public function testReturns404ForMissingFile(): void
    {
        $action = $this->createAction();
        $response = $action(new ServerRequest(uri: '/nonexistent/'));

        assertSame(404, $response->getStatusCode());
    }

    public function testReturns404ForMissingOutputDirectory(): void
    {
        $action = new StaticFileAction(
            new ResponseFactory(),
            new StreamFactory(),
            '/nonexistent/output',
            $this->createBuildRunner(),
        );

        $response = $action(new ServerRequest(uri: '/'));

        assertSame(404, $response->getStatusCode());
    }

    public function testPreventsDirectoryTraversal(): void
    {
        file_put_contents($this->tempDir . '/secret.txt', 'secret');
        mkdir($this->tempDir . '/public', 0o755, true);
        file_put_contents($this->tempDir . '/public/index.html', 'ok');

        $action = new StaticFileAction(
            new ResponseFactory(),
            new StreamFactory(),
            $this->tempDir . '/public',
            $this->createBuildRunner(),
        );

        $response = $action(new ServerRequest(uri: '/../secret.txt'));

        assertSame(404, $response->getStatusCode());
    }

    private function createAction(): StaticFileAction
    {
        return new StaticFileAction(
            new ResponseFactory(),
            new StreamFactory(),
            $this->tempDir,
            $this->createBuildRunner(),
        );
    }

    private function createBuildRunner(): SiteBuildRunner
    {
        return new SiteBuildRunner('/bin/true', '/tmp', '/tmp');
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

<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Web\DevServer;

use YiiPress\Build\BuildManifest;
use YiiPress\RuntimePaths;
use YiiPress\Web\DevServer\EditorLauncherInterface;
use YiiPress\Web\DevServer\OpenSourceAction;
use FilesystemIterator;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequest;
use HttpSoft\Message\StreamFactory;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function hash;
use function mkdir;
use function sys_get_temp_dir;

final class OpenSourceActionTest extends TestCase
{
    private string $root;
    private StreamFactory $streamFactory;
    private FakeEditorLauncher $editorLauncher;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-open-source-action-' . uniqid();
        mkdir($this->root . '/content/blog', 0o755, true);
        mkdir($this->root . '/output/blog/post', 0o755, true);
        mkdir(RuntimePaths::cachePath($this->root), 0o755, true);
        $this->streamFactory = new StreamFactory();
        $this->editorLauncher = new FakeEditorLauncher();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testRejectsMissingPath(): void
    {
        $response = $this->action()($this->request('{}'));

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('Missing path', (string) $response->getBody());
    }

    public function testReturnsNotFoundWhenPageHasNoMarkdownSource(): void
    {
        file_put_contents($this->root . '/output/blog/post/index.html', '<html><body>Post</body></html>');

        $response = $this->action()($this->request('{"path":"/blog/post/"}'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testOpensResolvedMarkdownSource(): void
    {
        $sourceFile = $this->root . '/content/blog/post.md';
        $outputFile = $this->root . '/output/blog/post/index.html';
        file_put_contents($sourceFile, "---\ntitle: Post\n---\nBody");
        file_put_contents($outputFile, '<html><body>Post</body></html>');
        file_put_contents($this->root . '/content/config.yaml', "title: Test\nlanguages: [en]\neditor: code\n");

        $manifest = new BuildManifest($this->manifestPath());
        $manifest->record($sourceFile, [$outputFile]);
        $manifest->save();

        $response = $this->action()($this->request('{"path":"/blog/post/"}'));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame($sourceFile, $this->editorLauncher->openedFile);
        self::assertSame('code', $this->editorLauncher->configuredEditor);
    }

    private function action(): OpenSourceAction
    {
        return new OpenSourceAction(new ResponseFactory(), $this->streamFactory, $this->editorLauncher);
    }

    private function request(string $body): ServerRequest
    {
        $stream = $this->streamFactory->createStream($body);

        return new ServerRequest(
            method: 'POST',
            uri: '/_open-source',
            body: $stream,
            serverParams: [
                'YIIPRESS_PROJECT_ROOT' => $this->root,
                'YIIPRESS_CONTENT_DIR' => $this->root . '/content',
                'YIIPRESS_OUTPUT_DIR' => $this->root . '/output',
            ],
        );
    }

    private function manifestPath(): string
    {
        return RuntimePaths::cachePath($this->root) . '/build-manifest-' . hash('xxh128', $this->root . '/output') . '.json';
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

final class FakeEditorLauncher implements EditorLauncherInterface
{
    public ?string $openedFile = null;
    public mixed $configuredEditor = null;

    public function open(string $filePath, string|array|null $configuredEditor): bool
    {
        $this->openedFile = $filePath;
        $this->configuredEditor = $configuredEditor;

        return true;
    }

    public function configuredEditorFromFile(string $configPath): string|array|null
    {
        return 'code';
    }
}

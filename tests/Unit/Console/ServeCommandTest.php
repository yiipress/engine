<?php

declare(strict_types=1);

namespace App\Tests\Unit\Console;

use App\Console\ServeCommand;
use Evenement\EventEmitter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use React\Socket\ConnectionInterface;
use React\Stream\WritableStreamInterface;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Tester\CommandTester;
use Yiisoft\Yii\Console\ExitCode;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

use function chdir;
use function getcwd;
use function mkdir;
use function sys_get_temp_dir;

final class ServeCommandTest extends TestCase
{
    #[Test]
    public function serveTestModeDoesNotRequireLocalPublicDirectory(): void
    {
        $tester = new CommandTester(new ServeCommand());

        $exitCode = $tester->execute(['--env' => 'test']);

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertStringContainsString('ReactPHP preview server', $tester->getDisplay());
    }

    #[Test]
    public function serveShowsConfiguredWorkers(): void
    {
        $tester = new CommandTester(new ServeCommand());

        $exitCode = $tester->execute(['--env' => 'test', '--workers' => '4']);

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertStringContainsString('Workers', $tester->getDisplay());
        self::assertStringContainsString('4', $tester->getDisplay());
    }

    #[Test]
    public function serveCreatesFreshHttpRunnerPerRequest(): void
    {
        $command = new ServeCommand();
        $method = new ReflectionMethod($command, 'createHttpRunner');

        $firstRunner = $method->invoke($command);
        $secondRunner = $method->invoke($command);

        self::assertInstanceOf(HttpApplicationRunner::class, $firstRunner);
        self::assertInstanceOf(HttpApplicationRunner::class, $secondRunner);
        self::assertNotSame($firstRunner, $secondRunner);
    }

    #[Test]
    public function serveCreatesEmptyRequestBodyWithoutOpeningAFile(): void
    {
        $command = new ServeCommand();
        $method = new ReflectionMethod($command, 'createRequest');

        $request = $method->invoke($command, "HEAD / HTTP/1.1\r\nHost: example.test\r\n\r\n", 'example.test:8080');

        self::assertInstanceOf(ServerRequestInterface::class, $request);
        self::assertSame('', $request->getBody()->getContents());
    }

    #[Test]
    public function serveDetectsLiveReloadRequestBeforeYiiDispatch(): void
    {
        $command = new ServeCommand();
        $method = new ReflectionMethod($command, 'isLiveReloadRequest');

        self::assertTrue($method->invoke($command, "GET /_live-reload HTTP/1.1\r\nHost: example.test\r\n\r\n"));
        self::assertTrue($method->invoke($command, "GET /_live-reload?since=1 HTTP/1.1\r\nHost: example.test\r\n\r\n"));
        self::assertFalse($method->invoke($command, "POST /_live-reload HTTP/1.1\r\nHost: example.test\r\n\r\n"));
        self::assertFalse($method->invoke($command, "GET /blog/ HTTP/1.1\r\nHost: example.test\r\n\r\n"));
    }

    #[Test]
    public function serveCreatesStaticResponseWithoutYiiDispatch(): void
    {
        $previousDirectory = getcwd();
        self::assertIsString($previousDirectory);

        $root = sys_get_temp_dir() . '/yiipress-serve-static-' . uniqid();
        mkdir($root . '/output/blog', 0o755, true);
        file_put_contents($root . '/output/blog/index.html', '<html><body><h1>Blog</h1></body></html>');

        try {
            chdir($root);

            $command = new ServeCommand();
            $method = new ReflectionMethod($command, 'createStaticResponse');

            $response = $method->invoke($command, "GET /blog/ HTTP/1.1\r\nHost: example.test\r\n\r\n");
        } finally {
            chdir($previousDirectory);
            unlink($root . '/output/blog/index.html');
            rmdir($root . '/output/blog');
            rmdir($root . '/output');
            rmdir($root);
        }

        self::assertIsArray($response);
        self::assertSame(200, $response['status']);
        self::assertIsArray($response['headers']);
        self::assertArrayHasKey('Content-Type', $response['headers']);
        self::assertIsString($response['headers']['Content-Type']);
        self::assertIsString($response['body']);
        self::assertSame('text/html; charset=utf-8', $response['headers']['Content-Type']);
        self::assertStringContainsString('<h1>Blog</h1>', $response['body']);
        self::assertStringContainsString('EventSource("/_live-reload")', $response['body']);
    }

    #[Test]
    public function serveStreamsNonHtmlStaticResponse(): void
    {
        $previousDirectory = getcwd();
        self::assertIsString($previousDirectory);

        $root = sys_get_temp_dir() . '/yiipress-serve-static-' . uniqid();
        mkdir($root . '/output/assets', 0o755, true);
        file_put_contents($root . '/output/assets/image.jpg', 'image-bytes');

        try {
            chdir($root);

            $command = new ServeCommand();
            $method = new ReflectionMethod($command, 'createStaticResponse');

            $response = $method->invoke($command, "GET /assets/image.jpg HTTP/1.1\r\nHost: example.test\r\n\r\n");
        } finally {
            chdir($previousDirectory);
            unlink($root . '/output/assets/image.jpg');
            rmdir($root . '/output/assets');
            rmdir($root . '/output');
            rmdir($root);
        }

        self::assertIsArray($response);
        self::assertSame(200, $response['status']);
        self::assertIsArray($response['headers']);
        self::assertSame('image/jpeg', $response['headers']['Content-Type']);
        self::assertNull($response['body']);
        self::assertIsString($response['file']);
        self::assertStringEndsWith('/output/assets/image.jpg', $response['file']);
    }

    #[Test]
    public function servePreventsStaticPathTraversal(): void
    {
        $previousDirectory = getcwd();
        self::assertIsString($previousDirectory);

        $root = sys_get_temp_dir() . '/yiipress-serve-static-' . uniqid();
        mkdir($root . '/output', 0o755, true);
        file_put_contents($root . '/secret.txt', 'secret');

        try {
            chdir($root);

            $command = new ServeCommand();
            $method = new ReflectionMethod($command, 'createStaticResponse');

            $response = $method->invoke($command, "GET /../secret.txt HTTP/1.1\r\nHost: example.test\r\n\r\n");
        } finally {
            chdir($previousDirectory);
            unlink($root . '/secret.txt');
            rmdir($root . '/output');
            rmdir($root);
        }

        self::assertIsArray($response);
        self::assertSame(404, $response['status']);
    }

    #[Test]
    public function serveKeepsOneLiveReloadWatcherForMultipleClients(): void
    {
        $command = new ServeCommand();
        $writeMethod = new ReflectionMethod($command, 'writeLiveReloadResponse');
        $closeMethod = new ReflectionMethod($command, 'closeLiveReloadWatcher');
        $streamProperty = new ReflectionProperty($command, 'liveReloadStream');
        $clientsProperty = new ReflectionProperty($command, 'liveReloadClients');

        $firstConnection = new FakeConnection();
        $secondConnection = new FakeConnection();

        try {
            $writeMethod->invoke($command, $firstConnection);
            $firstStream = $streamProperty->getValue($command);

            $writeMethod->invoke($command, $secondConnection);
            $secondStream = $streamProperty->getValue($command);
            $clients = $clientsProperty->getValue($command);

            self::assertSame($firstStream, $secondStream);
            self::assertIsArray($clients);
            self::assertCount(2, $clients);
            self::assertStringContainsString('Content-Type: text/event-stream', $firstConnection->written);
            self::assertStringContainsString('retry: 1000', $secondConnection->written);
        } finally {
            $closeMethod->invoke($command);
        }
    }

    #[Test]
    public function serveWaitsForCompleteRequestBody(): void
    {
        $command = new ServeCommand();
        $method = new ReflectionMethod($command, 'requestLength');

        self::assertNull($method->invoke($command, "POST / HTTP/1.1\r\nHost: example.test\r\nContent-Length: 4"));
        self::assertNull($method->invoke($command, "POST / HTTP/1.1\r\nHost: example.test\r\nContent-Length: 4\r\n\r\nabc"));
        self::assertSame(
            62,
            $method->invoke($command, "POST / HTTP/1.1\r\nHost: example.test\r\nContent-Length: 4\r\n\r\nabcd"),
        );
    }
}

final class FakeConnection extends EventEmitter implements ConnectionInterface
{
    public string $written = '';
    private bool $closed = false;

    public function getRemoteAddress(): ?string
    {
        return 'tcp://127.0.0.1:12345';
    }

    public function getLocalAddress(): ?string
    {
        return 'tcp://127.0.0.1:8080';
    }

    public function isReadable(): bool
    {
        return !$this->closed;
    }

    public function pause(): void
    {
    }

    public function resume(): void
    {
    }

    public function pipe(WritableStreamInterface $dest, array $options = []): WritableStreamInterface
    {
        return $dest;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->emit('close');
    }

    public function isWritable(): bool
    {
        return !$this->closed;
    }

    public function write($data): bool
    {
        if ($this->closed) {
            return false;
        }

        $this->written .= (string) $data;

        return true;
    }

    public function end($data = null): void
    {
        if ($data !== null) {
            $this->write($data);
        }

        $this->close();
    }
}

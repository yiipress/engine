<?php

declare(strict_types=1);

namespace App\Tests\Unit\Console;

use App\Console\ServeCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use Symfony\Component\Console\Tester\CommandTester;
use Yiisoft\Yii\Console\ExitCode;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

final class ServeCommandTest extends TestCase
{
    #[Test]
    public function packagedServeTestModeDoesNotRequireLocalPublicDirectory(): void
    {
        $tester = new CommandTester(new ServeCommand(packaged: true));

        $exitCode = $tester->execute(['--env' => 'test']);

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertStringContainsString('embedded PHAR application', $tester->getDisplay());
    }

    #[Test]
    public function packagedServeShowsConfiguredWorkers(): void
    {
        $tester = new CommandTester(new ServeCommand(packaged: true));

        $exitCode = $tester->execute(['--env' => 'test', '--workers' => '4']);

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertStringContainsString('Workers', $tester->getDisplay());
        self::assertStringContainsString('4', $tester->getDisplay());
    }

    #[Test]
    public function packagedServeCreatesFreshHttpRunnerPerRequest(): void
    {
        $command = new ServeCommand(packaged: true);
        $method = new ReflectionMethod($command, 'createHttpRunner');

        $firstRunner = $method->invoke($command);
        $secondRunner = $method->invoke($command);

        self::assertInstanceOf(HttpApplicationRunner::class, $firstRunner);
        self::assertInstanceOf(HttpApplicationRunner::class, $secondRunner);
        self::assertNotSame($firstRunner, $secondRunner);
    }

    #[Test]
    public function packagedServeCreatesEmptyRequestBodyWithoutOpeningAFile(): void
    {
        $command = new ServeCommand(packaged: true);
        $method = new ReflectionMethod($command, 'createRequest');

        $request = $method->invoke($command, "HEAD / HTTP/1.1\r\nHost: example.test\r\n\r\n", 'example.test:8080');

        self::assertInstanceOf(ServerRequestInterface::class, $request);
        self::assertSame('', $request->getBody()->getContents());
    }

    #[Test]
    public function packagedServeDetectsLiveReloadRequestBeforeYiiDispatch(): void
    {
        $command = new ServeCommand(packaged: true);
        $method = new ReflectionMethod($command, 'isPackagedLiveReloadRequest');

        self::assertTrue($method->invoke($command, "GET /_live-reload HTTP/1.1\r\nHost: example.test\r\n\r\n"));
        self::assertTrue($method->invoke($command, "GET /_live-reload?since=1 HTTP/1.1\r\nHost: example.test\r\n\r\n"));
        self::assertFalse($method->invoke($command, "POST /_live-reload HTTP/1.1\r\nHost: example.test\r\n\r\n"));
        self::assertFalse($method->invoke($command, "GET /blog/ HTTP/1.1\r\nHost: example.test\r\n\r\n"));
    }

    #[Test]
    public function packagedServeWaitsForCompleteRequestBody(): void
    {
        $command = new ServeCommand(packaged: true);
        $method = new ReflectionMethod($command, 'requestLength');

        self::assertNull($method->invoke($command, "POST / HTTP/1.1\r\nHost: example.test\r\nContent-Length: 4"));
        self::assertNull($method->invoke($command, "POST / HTTP/1.1\r\nHost: example.test\r\nContent-Length: 4\r\n\r\nabc"));
        self::assertSame(
            62,
            $method->invoke($command, "POST / HTTP/1.1\r\nHost: example.test\r\nContent-Length: 4\r\n\r\nabcd"),
        );
    }
}

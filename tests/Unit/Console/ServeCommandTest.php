<?php

declare(strict_types=1);

namespace App\Tests\Unit\Console;

use App\Console\ServeCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Socket;
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
    public function packagedServeIgnoresDisconnectedClientSocketWrites(): void
    {
        $sockets = [];
        self::assertTrue(socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets));
        self::assertCount(2, $sockets);

        $serverSocket = $sockets[0];
        $clientSocket = $sockets[1];
        self::assertInstanceOf(Socket::class, $serverSocket);
        self::assertInstanceOf(Socket::class, $clientSocket);

        socket_close($clientSocket);

        $command = new ServeCommand(packaged: true);
        $method = new ReflectionMethod($command, 'writeSocket');

        $warnings = [];
        set_error_handler(
            static function (int $severity, string $message) use (&$warnings): bool {
                $warnings[] = [$severity, $message];

                return true;
            },
        );

        try {
            self::assertFalse($method->invoke($command, $serverSocket, "HTTP/1.1 200 OK\r\n\r\n"));
            self::assertSame([], $warnings);
        } finally {
            restore_error_handler();
            socket_close($serverSocket);
        }
    }
}

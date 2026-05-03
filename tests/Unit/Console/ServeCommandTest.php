<?php

declare(strict_types=1);

namespace App\Tests\Unit\Console;

use App\Console\ServeCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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
}

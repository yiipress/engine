<?php

declare(strict_types=1);

namespace App\Tests\Unit\Console;

use App\Console\ServeCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Yiisoft\Yii\Console\ExitCode;

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
}

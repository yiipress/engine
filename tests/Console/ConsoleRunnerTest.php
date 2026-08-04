<?php

declare(strict_types=1);

namespace YiiPress\Tests\Console;

use PHPUnit\Framework\TestCase;
use YiiPress\ApplicationInfo;

use function escapeshellarg;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class ConsoleRunnerTest extends TestCase
{
    public function testConsoleRunsWithApplicationNameAndVersion(): void
    {
        $yii = dirname(__DIR__, 2) . '/yii';

        exec($yii . ' 2>&1', $output, $exitCode);

        assertSame(0, $exitCode);
        assertStringContainsString('YiiPress ' . ApplicationInfo::version(), implode("\n", $output));
        self::assertStringNotContainsString('Yii Console', implode("\n", $output));
        assertStringNotContainsString('Runs an internal portable worker job', implode("\n", $output));
    }

    public function testCommandErrorRespectsVerbosity(): void
    {
        $yii = escapeshellarg(dirname(__DIR__, 2) . '/yii');

        exec($yii . ' new 2>&1', $defaultOutput, $defaultExitCode);
        exec($yii . ' new -vvv 2>&1', $debugOutput, $debugExitCode);

        $defaultOutput = implode("\n", $defaultOutput);
        $debugOutput = implode("\n", $debugOutput);

        self::assertSame(1, $defaultExitCode);
        self::assertSame(1, $debugExitCode);
        self::assertStringContainsString('Not enough arguments', $defaultOutput);
        self::assertStringNotContainsString('Message context:', $defaultOutput);
        self::assertStringNotContainsString('Stack trace:', $defaultOutput);
        self::assertStringContainsString('Stack trace:', $debugOutput);
    }
}

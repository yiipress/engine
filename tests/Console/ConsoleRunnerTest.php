<?php

declare(strict_types=1);

namespace YiiPress\Tests\Console;

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class ConsoleRunnerTest extends TestCase
{
    public function testConsoleRunsWithApplicationNameAndVersion(): void
    {
        $yii = dirname(__DIR__, 2) . '/yii';

        exec($yii . ' 2>&1', $output, $exitCode);

        assertSame(0, $exitCode);
        assertStringContainsString('YiiPress 1.0.0', implode("\n", $output));
        self::assertStringNotContainsString('Yii Console', implode("\n", $output));
    }
}

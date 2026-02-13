<?php

declare(strict_types=1);

namespace App\Tests\Console;

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class HelloCommandTest extends TestCase
{
    public function testHelloCommandOutputsGreeting(): void
    {
        $yii = dirname(__DIR__, 2) . '/yii';

        exec($yii . ' hello 2>&1', $output, $exitCode);

        assertSame(0, $exitCode);
        assertStringContainsString('Hello!', implode("\n", $output));
    }

    public function testYiiConsoleRuns(): void
    {
        $yii = dirname(__DIR__, 2) . '/yii';

        exec($yii . ' 2>&1', $output, $exitCode);

        assertSame(0, $exitCode);
        assertStringContainsString('Yii Console', implode("\n", $output));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Environment;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class EnvironmentTest extends TestCase
{
    protected function setUp(): void
    {
        Environment::prepare();
    }

    public function testAppEnv(): void
    {
        assertSame('test', Environment::appEnv());
    }
}

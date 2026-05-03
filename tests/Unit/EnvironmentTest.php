<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit;

use YiiPress\Environment;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function getenv;
use function putenv;

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

    public function testUsesDefaultEnvironmentWhenAppEnvIsMissing(): void
    {
        $previousEnvironment = getenv('APP_ENV');
        $previousEnvValue = $_ENV['APP_ENV'] ?? null;
        putenv('APP_ENV');
        unset($_ENV['APP_ENV']);

        try {
            Environment::prepare(Environment::PROD);

            assertSame('prod', Environment::appEnv());
        } finally {
            if ($previousEnvironment === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV=' . $previousEnvironment);
            }

            if ($previousEnvValue === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $previousEnvValue;
            }

            Environment::prepare();
        }
    }
}

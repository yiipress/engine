<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Http;

use PHPUnit\Framework\TestCase;
use YiiPress\Extension\Http\AvailableHttpClient;
use YiiPress\Extension\Install\InstallException;

final class AvailableHttpClientTest extends TestCase
{
    public function testPrefersStreamsWhenHttpsWrapperExists(): void
    {
        $client = new AvailableHttpClient(self::runner(0), httpsStreamAvailable: true);

        self::assertSame('stream', $client->transport());
    }

    public function testFallsBackToCurl(): void
    {
        $client = new AvailableHttpClient(self::runner(0), httpsStreamAvailable: false);

        self::assertSame('curl', $client->transport());
    }

    public function testFailsWithoutAnyTransport(): void
    {
        $client = new AvailableHttpClient(self::runner(127), httpsStreamAvailable: false);

        try {
            $client->transport();
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('http_client_missing', $exception->errorCode);
            self::assertStringContainsString('distroless', (string) $exception->getSolution());
        }
    }

    /**
     * @return callable(list<string>): array{exitCode: int, stdout: string, stderr: string}
     */
    private static function runner(int $exitCode): callable
    {
        return static fn(array $command): array => ['exitCode' => $exitCode, 'stdout' => '', 'stderr' => ''];
    }
}

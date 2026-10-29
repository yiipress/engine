<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Http;

use PHPUnit\Framework\TestCase;
use YiiPress\Extension\Http\HttpClientInterface;
use YiiPress\Extension\Http\HttpResponse;
use YiiPress\Extension\Http\RetryingHttpClient;
use YiiPress\Extension\Install\InstallException;

use function array_shift;

final class RetryingHttpClientTest extends TestCase
{
    public function testRetriesServerErrorWithBackoff(): void
    {
        $inner = self::client([new HttpResponse(503), new HttpResponse(502), new HttpResponse(200)]);
        $slept = [];

        $response = new RetryingHttpClient($inner, static function (int $s) use (&$slept): void {
            $slept[] = $s;
        })->get('u', 'f', 1);

        self::assertSame(200, $response->status);
        self::assertSame([1, 2], $slept);
    }

    public function testGivesUpAfterThreeRetries(): void
    {
        $inner = self::client([new HttpResponse(500), new HttpResponse(500), new HttpResponse(500), new HttpResponse(500)]);

        $response = new RetryingHttpClient($inner, static fn(int $s) => null)->get('u', 'f', 1);

        self::assertSame(500, $response->status);
    }

    public function testHonoursShortRetryAfterOn429(): void
    {
        $inner = self::client([new HttpResponse(429, ['retry-after' => ['7']]), new HttpResponse(200)]);
        $slept = [];

        new RetryingHttpClient($inner, static function (int $s) use (&$slept): void {
            $slept[] = $s;
        })->get('u', 'f', 1);

        self::assertSame([7], $slept);
    }

    public function testDoesNotRetry429WithLongOrMissingRetryAfter(): void
    {
        $long = new RetryingHttpClient(self::client([new HttpResponse(429, ['retry-after' => ['61']])]), static fn(int $s) => null);
        $none = new RetryingHttpClient(self::client([new HttpResponse(429)]), static fn(int $s) => null);

        self::assertSame(429, $long->get('u', 'f', 1)->status);
        self::assertSame(429, $none->get('u', 'f', 1)->status);
    }

    public function testRetriesNetworkFailureButNotOtherErrors(): void
    {
        $network = self::client([new InstallException('network_failed', 'x'), new HttpResponse(200)]);
        $redirect = self::client([new InstallException('redirect_refused', 'x'), new HttpResponse(200)]);

        self::assertSame(200, new RetryingHttpClient($network, static fn(int $s) => null)->get('u', 'f', 1)->status);
        $this->expectExceptionObject(new InstallException('redirect_refused', 'x'));
        new RetryingHttpClient($redirect, static fn(int $s) => null)->get('u', 'f', 1);
    }

    /**
     * @param list<HttpResponse|InstallException> $results
     */
    private static function client(array $results): HttpClientInterface
    {
        return new class ($results) implements HttpClientInterface {
            /**
             * @param list<HttpResponse|InstallException> $results
             */
            public function __construct(private array $results) {}

            public function get(string $url, string $destination, int $maxBytes): HttpResponse
            {
                $result = array_shift($this->results);
                if ($result instanceof InstallException) {
                    throw $result;
                }

                return $result ?? new HttpResponse(599);
            }
        };
    }
}

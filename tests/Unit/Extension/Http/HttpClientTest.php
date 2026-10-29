<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\Http\CurlHttpClient;
use YiiPress\Extension\Http\HttpClientInterface;
use YiiPress\Extension\Http\StreamHttpClient;
use YiiPress\Extension\Http\UrlPolicy;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Tests\Support\LocalHttpServer;

use function file_get_contents;
use function hrtime;
use function is_file;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Both transports against a local `php -S`, allowed by a test-only {@see UrlPolicy}.
 */
final class HttpClientTest extends TestCase
{
    private static LocalHttpServer $server;
    private string $file;

    public static function setUpBeforeClass(): void
    {
        self::$server = LocalHttpServer::start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/yiipress-http-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function clients(): iterable
    {
        yield 'stream' => ['stream'];
        yield 'curl' => ['curl'];
    }

    #[DataProvider('clients')]
    public function testDownloadsBodyAndHeaders(string $transport): void
    {
        $response = self::client($transport)->get(self::$server->baseUrl . '/ok', $this->file, 100);

        self::assertSame(200, $response->status);
        self::assertSame('yes', $response->header('X-Test'));
        self::assertSame('hello', file_get_contents($this->file));
    }

    #[DataProvider('clients')]
    public function testFollowsAllowedRedirect(string $transport): void
    {
        $response = self::client($transport)->get(self::$server->baseUrl . '/redirect', $this->file, 100);

        self::assertSame(200, $response->status);
        self::assertSame('hello', file_get_contents($this->file));
    }

    #[DataProvider('clients')]
    public function testReturnsErrorStatusWithoutThrowing(string $transport): void
    {
        $response = self::client($transport)->get(self::$server->baseUrl . '/missing', $this->file, 100);

        self::assertSame(404, $response->status);
    }

    #[DataProvider('clients')]
    public function testSendsUserAgent(string $transport): void
    {
        self::client($transport)->get(self::$server->baseUrl . '/agent', $this->file, 100);

        self::assertSame('YiiPress', file_get_contents($this->file));
    }

    #[DataProvider('clients')]
    public function testRefusesRedirectToOtherHost(string $transport): void
    {
        $this->expectFailure('redirect_refused', $transport, '/redirect-away', 100);
    }

    #[DataProvider('clients')]
    public function testRefusesRedirectLoop(string $transport): void
    {
        $this->expectFailure('redirect_refused', $transport, '/loop', 100);
    }

    #[DataProvider('clients')]
    public function testRefusesBodyOverLimit(string $transport): void
    {
        $this->expectFailure('download_too_large', $transport, '/big', 1000);
    }

    #[DataProvider('clients')]
    public function testRefusesUrlOutsidePolicy(string $transport): void
    {
        try {
            self::client($transport)->get('https://example.com/', $this->file, 100);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('redirect_refused', $exception->errorCode);
        }
    }

    #[DataProvider('clients')]
    public function testConnectionFailureIsNetworkFailed(string $transport): void
    {
        $client = $transport === 'stream'
            ? new StreamHttpClient(new UrlPolicy(['http'], ['127.0.0.1']))
            : new CurlHttpClient(policy: new UrlPolicy(['http'], ['127.0.0.1']));

        try {
            $client->get('http://127.0.0.1:1/', $this->file, 100);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('network_failed', $exception->errorCode);
        }
    }

    /**
     * The body arrives a byte every 0.2 s for 4 s: no read waits long, the whole request does.
     */
    public function testStreamRequestStopsAtWholeRequestTimeout(): void
    {
        $client = new StreamHttpClient(new UrlPolicy(['http'], ['127.0.0.1']), connectTimeout: 1, requestTimeout: 1);
        $start = hrtime(true);

        try {
            $client->get(self::$server->baseUrl . '/drip', $this->file, 100);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('network_failed', $exception->errorCode, $exception->getMessage());
            self::assertStringContainsString('timed out', $exception->getMessage());
        }
        self::assertLessThan(2.5, (hrtime(true) - $start) / 1e9);
        self::assertFileDoesNotExist($this->file);
    }

    private function expectFailure(string $code, string $transport, string $path, int $maxBytes): void
    {
        try {
            self::client($transport)->get(self::$server->baseUrl . $path, $this->file, $maxBytes);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame($code, $exception->errorCode, $exception->getMessage());
        }
    }

    private static function client(string $transport): HttpClientInterface
    {
        $policy = new UrlPolicy(['http'], ['127.0.0.1']);

        return $transport === 'stream' ? new StreamHttpClient($policy) : new CurlHttpClient(policy: $policy);
    }
}

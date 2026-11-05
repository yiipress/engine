<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Http;

use PHPUnit\Framework\TestCase;
use YiiPress\Extension\Http\CurlHttpClient;

use function array_search;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function is_dir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * curl writes into a private directory, never to a path another local user could predict and claim first.
 */
final class CurlHttpClientTest extends TestCase
{
    private string $destination;

    protected function setUp(): void
    {
        $this->destination = (string) tempnam(sys_get_temp_dir(), 'yiipress-curl-test-');
    }

    protected function tearDown(): void
    {
        @unlink($this->destination);
    }

    public function testCurlWritesIntoPrivateDirectoryAndResultIsMovedToDestination(): void
    {
        $seen = [];
        $client = new CurlHttpClient(function (array $command) use (&$seen): array {
            $output = $command[(int) array_search('--output', $command, true) + 1];
            $headers = $command[(int) array_search('--dump-header', $command, true) + 1];
            $seen = [
                'first' => $command[1],
                'output' => $output,
                'outputDirMode' => fileperms(dirname($output)) & 0o777,
                'headersDir' => dirname($headers),
                'destinationKept' => file_exists($this->destination),
            ];
            file_put_contents($output, 'body');
            file_put_contents($headers, "HTTP/2 200\r\nx-test: yes\r\n\r\n");

            return ['exitCode' => 0, 'stdout' => '200 https://api.github.com/x', 'stderr' => ''];
        });

        $response = $client->get('https://api.github.com/x', $this->destination, 100);

        self::assertSame('-q', $seen['first'], 'curl must ignore ~/.curlrc');
        self::assertNotSame($this->destination, $seen['output']);
        self::assertSame(0o700, $seen['outputDirMode']);
        self::assertSame(dirname($seen['output']), $seen['headersDir']);
        self::assertTrue($seen['destinationKept'], 'the private temporary file was unlinked before curl ran');
        self::assertSame('body', file_get_contents($this->destination));
        self::assertSame('yes', $response->header('x-test'));
        self::assertFalse(is_dir(dirname($seen['output'])), 'the private directory is removed');
    }
}

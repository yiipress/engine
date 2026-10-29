<?php

declare(strict_types=1);

namespace YiiPress\Tests\Support;

use RuntimeException;

use function fclose;
use function fsockopen;
use function is_resource;
use function proc_open;
use function proc_terminate;
use function random_int;
use function usleep;

use const PHP_BINARY;

/**
 * Starts `php -S` on 127.0.0.1 with the HTTP fixture router.
 */
final class LocalHttpServer
{
    private function __construct(public readonly string $baseUrl, private mixed $process) {}

    public static function start(): self
    {
        $port = random_int(20_000, 40_000);
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/http-fixture-router.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start php -S.');
        }
        for ($i = 0; $i < 100; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.1);
            if ($socket !== false) {
                fclose($socket);

                return new self('http://127.0.0.1:' . $port, $process);
            }
            usleep(20_000);
        }
        proc_terminate($process);

        throw new RuntimeException('php -S did not start.');
    }

    public function stop(): void
    {
        proc_terminate($this->process);
    }
}

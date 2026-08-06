<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Update;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use YiiPress\Update\AvailableUrlDownloader;

use function array_filter;
use function file_get_contents;
use function file_put_contents;
use function mb_check_encoding;
use function str_repeat;
use function sys_get_temp_dir;
use function unlink;
use function uniqid;

final class AvailableUrlDownloaderTest extends TestCase
{
    private string $destination;

    protected function setUp(): void
    {
        $this->destination = sys_get_temp_dir() . '/yiipress-downloader-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        @unlink($this->destination);
    }

    #[Test]
    public function prefersCurlAndCachesSelection(): void
    {
        $commands = [];
        $runner = static function (array $command, array $environment = []) use (&$commands): array {
            $commands[] = $command;
            if ($command === ['curl', '--version']) {
                return self::commandResult();
            }
            file_put_contents($command[14], 'downloaded');
            return self::commandResult();
        };
        $downloader = new AvailableUrlDownloader($runner, httpsStreamAvailable: true);

        $downloader->download('https://example.com/first', $this->destination);
        $downloader->download('https://example.com/second', $this->destination);

        self::assertSame('downloaded', file_get_contents($this->destination));
        self::assertSame(['curl', '--version'], $commands[0]);
        self::assertCount(1, array_filter($commands, static fn(array $command): bool => $command === ['curl', '--version']));
        self::assertSame('https://example.com/second', $commands[2][15]);
    }

    #[Test]
    public function downloadsWithAvailableHostTransport(): void
    {
        $source = sys_get_temp_dir() . '/yiipress-downloader-source-' . uniqid('', true);
        file_put_contents($source, 'host transport');

        try {
            (new AvailableUrlDownloader())->download('file://' . $source, $this->destination);
        } finally {
            unlink($source);
        }

        self::assertSame('host transport', file_get_contents($this->destination));
    }

    #[Test]
    public function usesPhpStreamsForNonHttpUrlsWithoutProbingPlatformCommands(): void
    {
        $commands = [];
        $runner = static function (array $command, array $environment = []) use (&$commands): array {
            $commands[] = $command;
            return self::commandResult(127);
        };
        $source = sys_get_temp_dir() . '/yiipress-downloader-source-' . uniqid('', true);
        file_put_contents($source, 'streamed');

        try {
            $downloader = new AvailableUrlDownloader($runner, httpsStreamAvailable: true);
            $downloader->download('file://' . $source, $this->destination);
        } finally {
            unlink($source);
        }

        self::assertSame('streamed', file_get_contents($this->destination));
        self::assertSame([], $commands);
    }

    #[Test]
    public function usesWgetOnUnix(): void
    {
        $commands = [];
        $runner = static function (array $command, array $environment = []) use (&$commands): array {
            $commands[] = $command;
            if ($command === ['wget', '--help']) {
                return self::commandResult();
            }
            if ($command[0] === 'wget') {
                file_put_contents($command[7], 'downloaded');
                return self::commandResult();
            }
            return self::commandResult(127);
        };
        $downloader = new AvailableUrlDownloader($runner, 'Linux', false);

        $downloader->download('https://example.com/package', $this->destination);

        self::assertSame('downloaded', file_get_contents($this->destination));
        self::assertSame(['curl', '--version'], $commands[0]);
        self::assertSame(['wget', '--help'], $commands[1]);
        self::assertSame('wget', $commands[2][0]);
    }

    #[Test]
    public function usesPowerShellOnWindows(): void
    {
        $commands = [];
        $environments = [];
        $runner = static function (array $command, array $environment = []) use (&$commands, &$environments): array {
            $commands[] = $command;
            $environments[] = $environment;
            if ($command[0] === 'pwsh' && $command[5] === 'exit 0') {
                return self::commandResult();
            }
            if ($command[0] === 'pwsh') {
                file_put_contents($environment['YIIPRESS_DOWNLOAD_DESTINATION'], 'downloaded');
                return self::commandResult();
            }
            return self::commandResult(127);
        };
        $downloader = new AvailableUrlDownloader($runner, 'Windows', false);

        $downloader->download('https://example.com/package', $this->destination);

        self::assertSame('downloaded', file_get_contents($this->destination));
        self::assertSame('pwsh', $commands[2][0]);
        self::assertStringContainsString('Invoke-WebRequest', $commands[2][5]);
        self::assertStringContainsString('$env:YIIPRESS_DOWNLOAD_URL', $commands[2][5]);
        self::assertSame('https://example.com/package', $environments[2]['YIIPRESS_DOWNLOAD_URL']);
        self::assertSame($this->destination, $environments[2]['YIIPRESS_DOWNLOAD_DESTINATION']);
    }

    #[Test]
    public function doesNotFallBackAfterTransferFailure(): void
    {
        $commands = [];
        $runner = static function (array $command, array $environment = []) use (&$commands): array {
            $commands[] = $command;
            return $command === ['curl', '--version']
                ? self::commandResult()
                : self::commandResult(22, 'certificate failure');
        };
        $downloader = new AvailableUrlDownloader($runner, 'Linux', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('certificate failure');

        try {
            $downloader->download('https://example.com/package', $this->destination);
        } finally {
            self::assertCount(2, $commands);
            self::assertFileDoesNotExist($this->destination);
        }
    }

    #[Test]
    public function reportsWhenNoDownloaderIsAvailable(): void
    {
        $downloader = new AvailableUrlDownloader(
            static fn(array $command, array $environment = []): array => self::commandResult(127),
            'Linux',
            false,
        );

        $this->expectExceptionMessage('Install curl and retry');
        $downloader->download('https://example.com/package', $this->destination);
    }

    #[Test]
    public function truncatesErrorDetailsWithoutSplittingUtf8Characters(): void
    {
        $stderr = str_repeat('a', 1_999) . '😀trailing';
        $downloader = new AvailableUrlDownloader(
            static fn(array $command, array $environment = []): array => $command === ['curl', '--version']
                ? self::commandResult()
                : self::commandResult(1, $stderr),
            'Linux',
            false,
        );

        try {
            $downloader->download('https://example.com/package', $this->destination);
            self::fail('Expected the download to fail.');
        } catch (RuntimeException $exception) {
            self::assertTrue(mb_check_encoding($exception->getMessage(), 'UTF-8'));
            self::assertStringEndsWith(str_repeat('a', 1_999) . '…', $exception->getMessage());
        }
    }

    /** @return array{exitCode: int, stdout: string, stderr: string} */
    private static function commandResult(int $exitCode = 0, string $stderr = ''): array
    {
        return ['exitCode' => $exitCode, 'stdout' => '', 'stderr' => $stderr];
    }
}

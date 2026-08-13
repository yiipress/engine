<?php

declare(strict_types=1);

namespace YiiPress\Update;

use Closure;
use RuntimeException;

use function fclose;
use function getenv;
use function in_array;
use function is_file;
use function is_resource;
use function is_string;
use function mb_strcut;
use function parse_url;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function stream_get_wrappers;
use function strlen;
use function strtolower;
use function trim;
use function unlink;

final class AvailableUrlDownloader implements UrlDownloaderInterface
{
    private const int MAX_ERROR_LENGTH = 2_000;

    /** @var Closure(list<string>, array<string, string>): array{exitCode: int, stdout: string, stderr: string} */
    private Closure $runner;
    private ?string $transport = null;

    /**
     * @param null|callable(list<string>, array<string, string>): array{exitCode: int, stdout: string, stderr: string} $runner
     */
    public function __construct(
        ?callable $runner = null,
        private readonly string $osFamily = PHP_OS_FAMILY,
        private readonly ?bool $httpsStreamAvailable = null,
    ) {
        $this->runner = $runner === null ? $this->run(...) : Closure::fromCallable($runner);
    }

    public function download(string $url, string $destination): void
    {
        @unlink($destination);

        if (!$this->isHttpUrl($url)) {
            new StreamUrlDownloader()->download($url, $destination);
            return;
        }

        $transport = $this->transport ??= $this->selectTransport();
        if ($transport === 'stream') {
            new StreamUrlDownloader()->download($url, $destination);
            return;
        }

        $command = match ($transport) {
            'curl' => [
                'curl', '--fail', '--location', '--silent', '--show-error', '--retry', '3',
                '--retry-delay', '2', '--connect-timeout', '30', '--max-time', '300',
                '--output', $destination, $url,
            ],
            'wget' => ['wget', '-q', '-T', '30', '-t', '3', '-O', $destination, $url],
            'pwsh', 'powershell.exe' => [
                $transport,
                '-NoLogo',
                '-NoProfile',
                '-NonInteractive',
                '-Command',
                '$ProgressPreference = "SilentlyContinue"; '
                . '$lastError = $null; '
                . 'for ($attempt = 1; $attempt -le 3; $attempt++) { '
                . 'try { Invoke-WebRequest -UseBasicParsing -TimeoutSec 300 '
                . '-Uri $env:YIIPRESS_DOWNLOAD_URL -OutFile $env:YIIPRESS_DOWNLOAD_DESTINATION; exit 0 } '
                . 'catch { $lastError = $_; if ($attempt -lt 3) { Start-Sleep -Seconds 2 } } }; '
                . 'Write-Error $lastError; exit 1',
            ],
            default => throw new RuntimeException("Unsupported download transport: $transport."),
        };

        $environment = in_array($transport, ['pwsh', 'powershell.exe'], true)
            ? ['YIIPRESS_DOWNLOAD_URL' => $url, 'YIIPRESS_DOWNLOAD_DESTINATION' => $destination]
            : [];
        $result = ($this->runner)($command, $environment);
        if ($result['exitCode'] !== 0 || !is_file($destination)) {
            @unlink($destination);
            $details = $this->errorDetails($result['stderr']);
            throw new RuntimeException("Could not download $url using $transport.$details");
        }
    }

    private function isHttpUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
    }

    private function selectTransport(): string
    {
        if ($this->available(['curl', '--version'])) {
            return 'curl';
        }

        if ($this->httpsStreamAvailable ?? in_array('https', stream_get_wrappers(), true)) {
            return 'stream';
        }

        if ($this->osFamily === 'Windows') {
            if ($this->available(['pwsh', '-NoLogo', '-NoProfile', '-NonInteractive', '-Command', 'exit 0'])) {
                return 'pwsh';
            }
            if ($this->available(['powershell.exe', '-NoLogo', '-NoProfile', '-NonInteractive', '-Command', 'exit 0'])) {
                return 'powershell.exe';
            }
        } elseif ($this->available(['wget', '--help'])) {
            return 'wget';
        }

        throw new RuntimeException(
            'Self-update requires curl or another HTTPS downloader. Install curl and retry. '
            . 'In a container, pull a newer YiiPress image instead.',
        );
    }

    /** @param list<string> $command */
    private function available(array $command): bool
    {
        return ($this->runner)($command, [])['exitCode'] === 0;
    }

    private function errorDetails(string $stderr): string
    {
        $stderr = trim($stderr);
        if ($stderr === '') {
            return '';
        }

        if (strlen($stderr) > self::MAX_ERROR_LENGTH) {
            $stderr = mb_strcut($stderr, 0, self::MAX_ERROR_LENGTH) . '…';
        }

        return ' ' . $stderr;
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function run(array $command, array $environment): array
    {
        $pipes = [];
        $inheritedEnvironment = getenv();
        $process = @proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            env_vars: $environment === []
                ? null
                : [...$inheritedEnvironment, ...$environment],
            options: ['bypass_shell' => true],
        );
        if (!is_resource($process)) {
            return ['exitCode' => 127, 'stdout' => '', 'stderr' => 'Could not start ' . $command[0] . '.'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'exitCode' => proc_close($process),
            'stdout' => $stdout === false ? '' : $stdout,
            'stderr' => $stderr === false ? '' : $stderr,
        ];
    }
}

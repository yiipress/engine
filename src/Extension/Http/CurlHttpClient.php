<?php

declare(strict_types=1);

namespace YiiPress\Extension\Http;

use Closure;
use YiiPress\Extension\Archive\InstallLimits;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Release\GitHubReleaseSource;
use Yiisoft\Yii\Console\ExitCode;

use function explode;
use function file;
use function filesize;
use function is_file;
use function mb_strcut;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strlen;
use function touch;
use function trim;
use function unlink;

use const FILE_IGNORE_NEW_LINES;

/**
 * Downloads with the system `curl` program, for builds of PHP without an `https` stream wrapper.
 */
final class CurlHttpClient implements HttpClientInterface
{
    private const int EXIT_UNSUPPORTED_PROTOCOL = 1;
    private const int EXIT_TOO_MANY_REDIRECTS = 47;
    private const int EXIT_FILE_TOO_LARGE = 63;
    private const int MAX_ERROR_LENGTH = 500;

    /** @var Closure(list<string>): array{exitCode: int, stdout: string, stderr: string} */
    private Closure $runner;

    /**
     * @param null|callable(list<string>): array{exitCode: int, stdout: string, stderr: string} $runner
     */
    public function __construct(
        ?callable $runner = null,
        private readonly UrlPolicy $policy = new UrlPolicy(),
        private readonly string $binary = 'curl',
    ) {
        $this->runner = $runner === null ? new ProcessRunner()->run(...) : Closure::fromCallable($runner);
    }

    public function get(string $url, string $destination, int $maxBytes): HttpResponse
    {
        $this->policy->check($url, $url);
        $headerFile = $destination . '.headers';
        @unlink($destination);

        try {
            $result = ($this->runner)([
                $this->binary,
                '--proto', $this->policy->curlProtocols(),
                '--proto-redir', $this->policy->curlProtocols(),
                '--location',
                '--max-redirs', (string) InstallLimits::REDIRECTS,
                '--max-filesize', (string) $maxBytes,
                '--connect-timeout', (string) InstallLimits::CONNECT_TIMEOUT_SECONDS,
                '--max-time', (string) InstallLimits::REQUEST_TIMEOUT_SECONDS,
                '--user-agent', 'YiiPress',
                '--silent',
                '--show-error',
                '--dump-header', $headerFile,
                '--output', $destination,
                '--write-out', '%{http_code} %{url_effective}',
                $url,
            ]);
            $this->checkExitCode($result['exitCode'], $result['stderr'], $url, $maxBytes);

            [$status, $effectiveUrl] = $this->parseWriteOut($result['stdout'], $url);
            $this->policy->check($effectiveUrl, $url);
            if (!is_file($destination)) {
                // curl writes no file for an empty body.
                @touch($destination);
            }
            if ((int) @filesize($destination) > $maxBytes) {
                throw GitHubReleaseSource::tooLarge($url, $maxBytes);
            }

            return HttpResponse::fromHeaderLines($status, $this->lastHeaderBlock($headerFile));
        } finally {
            @unlink($headerFile);
        }
    }

    private function checkExitCode(int $exitCode, string $stderr, string $url, int $maxBytes): void
    {
        if ($exitCode === 0) {
            return;
        }
        if ($exitCode === self::EXIT_FILE_TOO_LARGE) {
            throw GitHubReleaseSource::tooLarge($url, $maxBytes);
        }
        if ($exitCode === self::EXIT_UNSUPPORTED_PROTOCOL || $exitCode === self::EXIT_TOO_MANY_REDIRECTS) {
            throw new InstallException('redirect_refused', sprintf(
                'The download of %s was redirected too often or to a URL that is not allowed.%s',
                $url,
                $this->details($stderr),
            ));
        }

        throw new InstallException(
            'network_failed',
            sprintf('Could not download %s with curl (exit code %d).%s', $url, $exitCode, $this->details($stderr)),
            ExitCode::UNAVAILABLE,
        );
    }

    /**
     * @return array{int, string}
     */
    private function parseWriteOut(string $stdout, string $url): array
    {
        if (preg_match('/^(\d{3}) (\S+)$/D', trim($stdout), $matches) !== 1) {
            throw new InstallException(
                'network_failed',
                sprintf('curl gave no HTTP status for %s.', $url),
                ExitCode::UNAVAILABLE,
            );
        }

        return [(int) $matches[1], $matches[2]];
    }

    /**
     * Header lines of the final response; curl writes one block per redirect.
     *
     * @return list<string>
     */
    private function lastHeaderBlock(string $headerFile): array
    {
        $lines = @file($headerFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }
        $block = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'HTTP/')) {
                $block = [];
            }
            if ($line !== '') {
                $block[] = $line;
            }
        }

        return $block;
    }

    private function details(string $stderr): string
    {
        $stderr = trim(explode("\n", trim($stderr))[0]);
        if ($stderr === '') {
            return '';
        }

        return ' ' . (strlen($stderr) > self::MAX_ERROR_LENGTH ? mb_strcut($stderr, 0, self::MAX_ERROR_LENGTH) . '…' : $stderr);
    }
}

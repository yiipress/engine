<?php

declare(strict_types=1);

namespace YiiPress\Extension\Http;

use YiiPress\Extension\Archive\InstallLimits;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Release\GitHubReleaseSource;
use Yiisoft\Yii\Console\ExitCode;

use function fclose;
use function feof;
use function fopen;
use function fread;
use function fwrite;
use function hrtime;
use function http_clear_last_response_headers;
use function http_get_last_response_headers;
use function in_array;
use function intdiv;
use function is_string;
use function max;
use function min;
use function parse_url;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function stream_context_create;
use function stream_get_meta_data;
use function stream_set_timeout;
use function strlen;
use function unlink;

/**
 * Downloads with PHP streams when PHP has an `https` wrapper. Redirects are followed by hand so that every
 * location passes {@see UrlPolicy}.
 */
final readonly class StreamHttpClient implements HttpClientInterface
{
    private const array REDIRECT_STATUSES = [301, 302, 303, 307, 308];
    private const int CHUNK_BYTES = 65_536;

    /**
     * @param float $connectTimeout Seconds to connect and between header reads.
     * @param float $requestTimeout Seconds for a whole request, redirects and the body included.
     */
    public function __construct(
        private UrlPolicy $policy = new UrlPolicy(),
        private float $connectTimeout = InstallLimits::CONNECT_TIMEOUT_SECONDS,
        private float $requestTimeout = InstallLimits::REQUEST_TIMEOUT_SECONDS,
    ) {}

    public function get(string $url, string $destination, int $maxBytes): HttpResponse
    {
        $current = $url;
        $deadline = hrtime(true) + (int) ($this->requestTimeout * 1e9);
        for ($redirects = 0; ; $redirects++) {
            $this->policy->check($current, $url);
            $response = $this->request($current, $destination, $maxBytes, $deadline);
            $location = $response->header('location');
            if (!in_array($response->status, self::REDIRECT_STATUSES, true) || $location === null) {
                return $response;
            }
            if ($redirects >= InstallLimits::REDIRECTS) {
                throw new InstallException('redirect_refused', sprintf(
                    'The download of %s was redirected more than %d times.',
                    $url,
                    InstallLimits::REDIRECTS,
                ));
            }
            $current = $this->resolve($location, $current);
        }
    }

    private function request(string $url, string $destination, int $maxBytes, int $deadline): HttpResponse
    {
        $context = stream_context_create([
            'http' => [
                'follow_location' => 0,
                'ignore_errors' => true,
                'timeout' => $this->connectTimeout,
                'user_agent' => 'YiiPress',
                'protocol_version' => 1.1,
                'header' => "Connection: close\r\n",
            ],
        ]);
        http_clear_last_response_headers();
        $source = @fopen($url, 'rb', false, $context);
        if ($source === false) {
            throw new InstallException(
                'network_failed',
                sprintf('Could not connect to download %s.', $url),
                ExitCode::UNAVAILABLE,
            );
        }

        try {
            if (hrtime(true) >= $deadline) {
                throw $this->timedOut($url);
            }
            $lines = http_get_last_response_headers() ?? [];
            $this->save($source, $destination, $maxBytes, $deadline, $url);
        } finally {
            fclose($source);
        }

        return HttpResponse::fromHeaderLines($this->status($lines, $url), $this->lastBlock($lines));
    }

    /**
     * Writes the body to `$destination`; nothing is left there when it fails.
     *
     * @param resource $source
     */
    private function save($source, string $destination, int $maxBytes, int $deadline, string $url): void
    {
        $target = @fopen($destination, 'wb');
        if ($target === false) {
            throw new InstallException('filesystem_failed', sprintf('Could not write "%s".', $destination));
        }
        try {
            $copied = $this->copy($source, $target, $maxBytes + 1, $deadline, $url);
        } catch (InstallException $exception) {
            fclose($target);
            @unlink($destination);

            throw $exception;
        }
        fclose($target);

        if ($copied === false) {
            @unlink($destination);
            throw new InstallException(
                'network_failed',
                sprintf('The download of %s was interrupted.', $url),
                ExitCode::UNAVAILABLE,
            );
        }
        if ($copied > $maxBytes) {
            @unlink($destination);
            throw GitHubReleaseSource::tooLarge($url, $maxBytes);
        }
    }

    /**
     * Copies up to `$limit` bytes, failing once the request has run past its deadline. Each read waits at most
     * for the time left, so a server that sends slowly but never pauses for long cannot stretch the request.
     *
     * @param resource $source
     * @param resource $target
     * @return int|false Bytes copied; `false` when the connection broke.
     *
     * @throws InstallException `network_failed` when the deadline passes.
     */
    private function copy($source, $target, int $limit, int $deadline, string $url): int|false
    {
        $copied = 0;
        while ($copied < $limit && !feof($source)) {
            $left = $deadline - hrtime(true);
            if ($left <= 0) {
                throw $this->timedOut($url);
            }
            stream_set_timeout($source, intdiv($left, 1_000_000_000), intdiv($left % 1_000_000_000, 1000));
            $chunk = fread($source, max(1, min(self::CHUNK_BYTES, $limit - $copied)));
            if (stream_get_meta_data($source)['timed_out']) {
                throw $this->timedOut($url);
            }
            if ($chunk === false) {
                return false;
            }
            if (fwrite($target, $chunk) !== strlen($chunk)) {
                return false;
            }
            $copied += strlen($chunk);
        }

        return $copied;
    }

    private function timedOut(string $url): InstallException
    {
        return new InstallException(
            'network_failed',
            sprintf('The download of %s timed out after %s seconds.', $url, $this->requestTimeout),
            ExitCode::UNAVAILABLE,
        );
    }

    /**
     * @param list<string> $lines
     */
    private function status(array $lines, string $url): int
    {
        $block = $this->lastBlock($lines);
        if ($block === [] || preg_match('~^HTTP/\S+ (\d{3})~', $block[0], $matches) !== 1) {
            throw new InstallException(
                'network_failed',
                sprintf('The server gave no HTTP status for %s.', $url),
                ExitCode::UNAVAILABLE,
            );
        }

        return (int) $matches[1];
    }

    /**
     * PHP lists the headers of informational responses too; the last status line starts the final ones.
     *
     * @param list<string> $lines
     * @return list<string>
     */
    private function lastBlock(array $lines): array
    {
        $block = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, 'HTTP/')) {
                $block = [];
            }
            $block[] = $line;
        }

        return $block;
    }

    private function resolve(string $location, string $base): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }
        $scheme = parse_url($base, PHP_URL_SCHEME);
        $authority = parse_url($base, PHP_URL_HOST);
        $port = parse_url($base, PHP_URL_PORT);
        if (!is_string($scheme) || !is_string($authority) || !str_starts_with($location, '/')) {
            return $location;
        }

        return $scheme . '://' . $authority . ($port === null ? '' : ':' . $port) . $location;
    }
}

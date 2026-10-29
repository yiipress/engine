<?php

declare(strict_types=1);

namespace YiiPress\Extension\Http;

use YiiPress\Extension\Archive\InstallLimits;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Release\GitHubReleaseSource;
use Yiisoft\Yii\Console\ExitCode;

use function fclose;
use function fopen;
use function http_clear_last_response_headers;
use function http_get_last_response_headers;
use function in_array;
use function is_string;
use function parse_url;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function stream_context_create;
use function stream_copy_to_stream;
use function unlink;

/**
 * Downloads with PHP streams when PHP has an `https` wrapper. Redirects are followed by hand so that every
 * location passes {@see UrlPolicy}.
 */
final readonly class StreamHttpClient implements HttpClientInterface
{
    private const array REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    public function __construct(
        private UrlPolicy $policy = new UrlPolicy(),
    ) {}

    public function get(string $url, string $destination, int $maxBytes): HttpResponse
    {
        $current = $url;
        for ($redirects = 0; ; $redirects++) {
            $this->policy->check($current, $url);
            $response = $this->request($current, $destination, $maxBytes);
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

    private function request(string $url, string $destination, int $maxBytes): HttpResponse
    {
        $context = stream_context_create([
            'http' => [
                'follow_location' => 0,
                'ignore_errors' => true,
                'timeout' => InstallLimits::REQUEST_TIMEOUT_SECONDS,
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
            $lines = http_get_last_response_headers() ?? [];
            $target = @fopen($destination, 'wb');
            if ($target === false) {
                throw new InstallException('filesystem_failed', sprintf('Could not write "%s".', $destination));
            }
            try {
                $copied = stream_copy_to_stream($source, $target, $maxBytes + 1);
            } finally {
                fclose($target);
            }
        } finally {
            fclose($source);
        }

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

        return HttpResponse::fromHeaderLines($this->status($lines, $url), $this->lastBlock($lines));
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

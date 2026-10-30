<?php

declare(strict_types=1);

namespace YiiPress\Extension\Http;

use Closure;
use YiiPress\Extension\Archive\InstallLimits;
use YiiPress\Extension\Install\InstallException;

use function ctype_digit;
use function sleep;

/**
 * Retries a request after a connection failure, a 5xx status, or a 429 status with a short `Retry-After`.
 */
final class RetryingHttpClient implements HttpClientInterface
{
    /** @var Closure(int): void */
    private Closure $sleep;

    /**
     * @param null|callable(int): void $sleep Waits the given number of seconds.
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        ?callable $sleep = null,
    ) {
        $this->sleep = $sleep === null ? static function (int $seconds): void {
            sleep($seconds);
        } : Closure::fromCallable($sleep);
    }

    public function get(string $url, string $destination, int $maxBytes): HttpResponse
    {
        for ($attempt = 0; ; $attempt++) {
            $backoff = 2 ** $attempt;
            try {
                $response = $this->client->get($url, $destination, $maxBytes);
            } catch (InstallException $exception) {
                if ($exception->errorCode !== 'network_failed' || $attempt >= InstallLimits::RETRIES) {
                    throw $exception;
                }
                ($this->sleep)($backoff);
                continue;
            }

            $delay = $this->retryDelay($response, $backoff);
            if ($delay === null || $attempt >= InstallLimits::RETRIES) {
                return $response;
            }
            ($this->sleep)($delay);
        }
    }

    private function retryDelay(HttpResponse $response, int $backoff): ?int
    {
        if ($response->status >= 500) {
            return $backoff;
        }
        if ($response->status !== 429) {
            return null;
        }
        $retryAfter = $response->header('retry-after');
        if ($retryAfter === null || !ctype_digit($retryAfter) || (int) $retryAfter > InstallLimits::MAX_RETRY_AFTER_SECONDS) {
            return null;
        }

        return (int) $retryAfter;
    }
}

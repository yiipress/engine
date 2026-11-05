<?php

declare(strict_types=1);

namespace YiiPress\Extension\Http;

use Closure;
use YiiPress\Extension\Install\InstallException;

use function in_array;
use function stream_get_wrappers;

/**
 * Uses PHP streams when PHP has an `https` wrapper, else the system `curl`, with retries.
 *
 * The static YiiPress binary has no `https` wrapper, and the distroless image has no `curl`;
 * there this client fails with `http_client_missing`.
 */
final class AvailableHttpClient implements HttpClientInterface
{
    /** @var Closure(list<string>): array{exitCode: int, stdout: string, stderr: string} */
    private Closure $runner;
    private ?HttpClientInterface $client = null;
    private string $transport = '';

    /**
     * @param null|callable(list<string>): array{exitCode: int, stdout: string, stderr: string} $runner
     * @param bool|null $httpsStreamAvailable Whether PHP has an `https` wrapper; detected when `null`.
     * @param Closure(int): void|null $sleep Waits between retries; `sleep()` when `null`.
     */
    public function __construct(
        ?callable $runner = null,
        private readonly ?bool $httpsStreamAvailable = null,
        private readonly UrlPolicy $policy = new UrlPolicy(),
        private readonly ?Closure $sleep = null,
    ) {
        $this->runner = $runner === null ? new ProcessRunner()->run(...) : Closure::fromCallable($runner);
    }

    public function get(string $url, string $destination, int $maxBytes): HttpResponse
    {
        return ($this->client ??= $this->select())->get($url, $destination, $maxBytes);
    }

    /**
     * The transport in use: `stream` or `curl`.
     */
    public function transport(): string
    {
        $this->client ??= $this->select();

        return $this->transport;
    }

    private function select(): HttpClientInterface
    {
        if ($this->httpsStreamAvailable ?? in_array('https', stream_get_wrappers(), true)) {
            $this->transport = 'stream';

            return new RetryingHttpClient(new StreamHttpClient($this->policy), $this->sleep);
        }
        if (($this->runner)(['curl', '--version'])['exitCode'] === 0) {
            $this->transport = 'curl';

            return new RetryingHttpClient(new CurlHttpClient($this->runner, $this->policy), $this->sleep);
        }

        throw new InstallException(
            'http_client_missing',
            'This YiiPress cannot download over HTTPS: PHP has no https stream wrapper and the curl program is missing.',
            solution: 'Install curl, or run this command outside the distroless YiiPress image.',
        );
    }
}

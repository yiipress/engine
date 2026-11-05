<?php

declare(strict_types=1);

namespace YiiPress\Extension\Http;

use YiiPress\Extension\Install\InstallException;

use function implode;
use function in_array;
use function is_string;
use function parse_url;
use function sprintf;
use function strtolower;

/**
 * Schemes and hosts an extension download may use, including every redirect.
 */
final readonly class UrlPolicy
{
    public const array GITHUB_HOSTS = [
        'github.com',
        'api.github.com',
        'codeload.github.com',
        'raw.githubusercontent.com',
        'objects.githubusercontent.com',
    ];

    /**
     * @param list<string> $schemes Lowercase.
     * @param list<string> $hosts Lowercase.
     */
    public function __construct(
        private array $schemes = ['https'],
        private array $hosts = self::GITHUB_HOSTS,
    ) {}

    public function allows(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($scheme)
            && is_string($host)
            && in_array(strtolower($scheme), $this->schemes, true)
            && in_array(strtolower($host), $this->hosts, true);
    }

    /**
     * @throws InstallException `redirect_refused` when the URL is not allowed.
     */
    public function check(string $url, string $requested): void
    {
        if (!$this->allows($url)) {
            throw new InstallException('redirect_refused', sprintf(
                'The download of %s was redirected to %s; only %s on %s is allowed.',
                $requested,
                $url,
                implode(', ', $this->schemes),
                implode(', ', $this->hosts),
            ));
        }
    }

    /**
     * Value for `curl --proto` and `--proto-redir`, for example `=https`.
     */
    public function curlProtocols(): string
    {
        return '=' . implode(',', $this->schemes);
    }
}

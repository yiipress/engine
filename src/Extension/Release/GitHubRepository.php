<?php

declare(strict_types=1);

namespace YiiPress\Extension\Release;

use Stringable;
use YiiPress\Extension\Install\InstallException;

use function is_string;
use function parse_url;
use function preg_match;
use function sprintf;
use function strtolower;

/**
 * A GitHub repository given as `https://github.com/<owner>/<repo>`.
 */
final readonly class GitHubRepository implements Stringable
{
    private const string PATH_PATTERN = '~^/([A-Za-z0-9](?:[A-Za-z0-9-]{0,38}))/([A-Za-z0-9._-]{1,100}?)(?:\.git)?/?$~D';

    private function __construct(
        public string $owner,
        public string $name,
    ) {}

    /**
     * @throws InstallException `source_invalid` or `source_unsupported`.
     */
    public static function fromUrl(string $url): self
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;
        if ($parts === false || ($parts['scheme'] ?? null) === null || !is_string($host)) {
            throw self::invalid($url);
        }
        if (strtolower($host) !== 'github.com') {
            throw new InstallException('source_unsupported', sprintf(
                'Only GitHub repositories are supported for now; "%s" is on %s.',
                $url,
                $host,
            ));
        }
        if (
            strtolower($parts['scheme']) !== 'https'
            || isset($parts['user'])
            || isset($parts['port'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || preg_match(self::PATH_PATTERN, $parts['path'] ?? '', $matches) !== 1
            || $matches[2] === '.'
            || $matches[2] === '..'
        ) {
            throw self::invalid($url);
        }

        return new self($matches[1], $matches[2]);
    }

    public function url(): string
    {
        return 'https://github.com/' . $this->owner . '/' . $this->name;
    }

    public function __toString(): string
    {
        return $this->owner . '/' . $this->name;
    }

    private static function invalid(string $url): InstallException
    {
        return new InstallException('source_invalid', sprintf(
            '"%s" is not a GitHub repository URL; use the form https://github.com/<owner>/<repository>.',
            $url,
        ));
    }
}

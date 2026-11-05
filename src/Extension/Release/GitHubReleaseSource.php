<?php

declare(strict_types=1);

namespace YiiPress\Extension\Release;

use JsonException;
use YiiPress\Extension\Archive\InstallLimits;
use YiiPress\Extension\Http\HttpClientInterface;
use YiiPress\Extension\Http\HttpResponse;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\SemanticVersion;
use Yiisoft\Yii\Console\ExitCode;

use function array_is_list;
use function date;
use function file_get_contents;
use function filesize;
use function hash_file;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * Lists the releases of a GitHub repository and fetches a release's manifest and source archive.
 */
final readonly class GitHubReleaseSource
{
    private const string API_HOST = 'https://api.github.com/';
    private const string API = self::API_HOST . 'repos/';
    private const string RAW = 'https://raw.githubusercontent.com/';
    private const string CODELOAD = 'https://codeload.github.com/';
    private const int MAX_PAGE_BYTES = 10 * 1024 * 1024;
    private const int MAX_MANIFEST_BYTES = 1024 * 1024;

    public function __construct(
        private HttpClientInterface $http,
        private InstallLimits $limits = new InstallLimits(),
    ) {}

    /**
     * Stable releases with a SemVer tag, in the order GitHub lists them. Drafts and pre-releases are left out.
     *
     * @return list<Release>
     */
    public function releases(GitHubRepository $repository): array
    {
        $releases = [];
        $url = self::API . $repository . '/releases?per_page=100';
        for ($page = 0; $url !== null && $page < InstallLimits::API_PAGES; $page++) {
            [$response, $body] = $this->fetch($url, self::MAX_PAGE_BYTES);
            if ($response->status === 404) {
                throw new InstallException('repository_not_found', sprintf(
                    'Repository %s was not found on GitHub, or it is private.',
                    $repository->url(),
                ));
            }
            $this->expectOk($response, $url);
            foreach ($this->decodeReleases($body, $url) as $release) {
                $releases[] = $release;
            }
            $url = $this->nextPage($response);
        }

        return $releases;
    }

    /**
     * Contents of `extension.yaml` at the release tag, `null` when the tag has none.
     */
    public function manifest(GitHubRepository $repository, Release $release): ?string
    {
        $url = self::RAW . $repository . '/' . $release->tag . '/extension.yaml';
        [$response, $body] = $this->fetch($url, self::MAX_MANIFEST_BYTES);
        if ($response->status === 404) {
            return null;
        }
        $this->expectOk($response, $url);

        return $body;
    }

    public function archiveUrl(GitHubRepository $repository, Release $release): string
    {
        return self::CODELOAD . $repository . '/tar.gz/refs/tags/' . $release->tag;
    }

    public function downloadArchive(GitHubRepository $repository, Release $release, string $file): DownloadedArchive
    {
        $url = $this->archiveUrl($repository, $release);
        $response = $this->http->get($url, $file, $this->limits->downloadBytes);
        if ($response->status === 404) {
            throw new InstallException('release_not_found', sprintf(
                'The source archive of %s %s is missing on GitHub.',
                $repository,
                $release->tag,
            ));
        }
        $this->expectOk($response, $url);

        $size = @filesize($file);
        if ($size === false) {
            throw new InstallException('filesystem_failed', sprintf('Could not read "%s".', $file));
        }
        if ($size > $this->limits->downloadBytes) {
            throw self::tooLarge($url, $this->limits->downloadBytes);
        }

        return new DownloadedArchive($file, $url, (string) hash_file('sha256', $file), $size);
    }

    public static function tooLarge(string $url, int $limit): InstallException
    {
        return new InstallException('download_too_large', sprintf('%s is larger than %d bytes.', $url, $limit));
    }

    /**
     * @return array{HttpResponse, string}
     */
    private function fetch(string $url, int $maxBytes): array
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-http-');
        if ($file === false) {
            throw new InstallException('filesystem_failed', 'Could not create a temporary file.');
        }
        try {
            $response = $this->http->get($url, $file, $maxBytes);
            $body = @file_get_contents($file);

            return [$response, $body === false ? '' : $body];
        } finally {
            @unlink($file);
        }
    }

    private function expectOk(HttpResponse $response, string $url): void
    {
        if ($response->status === 200) {
            return;
        }
        $remaining = $response->header('x-ratelimit-remaining');
        if ($response->status === 429 || ($response->status === 403 && $remaining === '0')) {
            $reset = $response->header('x-ratelimit-reset');
            throw new InstallException(
                'rate_limited',
                sprintf(
                    'GitHub refused the request for %s because the rate limit is reached%s; retry later.',
                    $url,
                    $reset !== null && is_numeric($reset) ? ' until ' . date('Y-m-d H:i:s T', (int) $reset) : '',
                ),
                ExitCode::UNAVAILABLE,
            );
        }

        throw new InstallException(
            'network_failed',
            sprintf('GitHub answered %s with HTTP status %d.', $url, $response->status),
            ExitCode::UNAVAILABLE,
        );
    }

    /**
     * @return list<Release>
     */
    private function decodeReleases(string $body, string $url): array
    {
        try {
            $items = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $items = null;
        }
        if (!is_array($items) || !array_is_list($items)) {
            throw new InstallException(
                'network_failed',
                sprintf('GitHub answered %s with something that is not a list of releases.', $url),
                ExitCode::UNAVAILABLE,
            );
        }

        $releases = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['tag_name'] ?? null)) {
                continue;
            }
            if (($item['draft'] ?? false) !== false || ($item['prerelease'] ?? false) !== false) {
                continue;
            }
            $release = self::release($item['tag_name']);
            if ($release !== null) {
                $releases[] = $release;
            }
        }

        return $releases;
    }

    /**
     * A release for a tag that is a stable SemVer version with at most one leading `v`, otherwise `null`.
     */
    public static function release(string $tag): ?Release
    {
        $version = str_starts_with($tag, 'v') ? substr($tag, 1) : $tag;
        if (!SemanticVersion::isValid($version) || str_contains($version, '-')) {
            return null;
        }

        return new Release($tag, $version);
    }

    private function nextPage(HttpResponse $response): ?string
    {
        $link = $response->header('link');
        if ($link === null || preg_match('/<([^>]+)>;\s*rel="next"/', $link, $matches) !== 1) {
            return null;
        }

        return str_starts_with($matches[1], self::API_HOST) ? $matches[1] : null;
    }
}

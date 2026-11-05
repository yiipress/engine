<?php

declare(strict_types=1);

namespace YiiPress\Tests\Support;

use YiiPress\Extension\Http\HttpClientInterface;
use YiiPress\Extension\Http\HttpResponse;
use YiiPress\Extension\Release\GitHubReleaseSource;

use function file_put_contents;
use function json_encode;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * Serves fixed responses by URL; an unknown URL answers 404. Records every requested URL.
 */
final class FakeHttpClient implements HttpClientInterface
{
    /** @var array<string, array{int, string, array<string, list<string>>}> */
    private array $routes = [];
    /** @var list<string> */
    public array $requests = [];

    /**
     * @param array<string, list<string>> $headers
     */
    public function route(string $url, string $body, int $status = 200, array $headers = []): self
    {
        $this->routes[$url] = [$status, $body, $headers];

        return $this;
    }

    /**
     * Serves a GitHub theme repository: the releases list, `extension.yaml` per tag and the tag archives.
     *
     * @param array<string, array{manifest?: string|null, archive?: string, draft?: bool, prerelease?: bool}> $releases
     *        Keyed by tag.
     */
    public function gitHub(string $repository, array $releases): self
    {
        $list = [];
        foreach ($releases as $tag => $release) {
            $list[] = [
                'tag_name' => $tag,
                'draft' => $release['draft'] ?? false,
                'prerelease' => $release['prerelease'] ?? false,
            ];
            if (($release['manifest'] ?? null) !== null) {
                $this->route("https://raw.githubusercontent.com/$repository/$tag/extension.yaml", $release['manifest']);
            }
            if (isset($release['archive'])) {
                $this->route("https://codeload.github.com/$repository/tar.gz/refs/tags/$tag", $release['archive']);
            }
        }

        return $this->route(
            "https://api.github.com/repos/$repository/releases?per_page=100",
            json_encode($list, JSON_THROW_ON_ERROR),
        );
    }

    public function get(string $url, string $destination, int $maxBytes): HttpResponse
    {
        $this->requests[] = $url;
        [$status, $body, $headers] = $this->routes[$url] ?? [404, 'Not Found', []];
        if (strlen($body) > $maxBytes) {
            throw GitHubReleaseSource::tooLarge($url, $maxBytes);
        }
        file_put_contents($destination, $body);

        return new HttpResponse($status, $headers);
    }
}

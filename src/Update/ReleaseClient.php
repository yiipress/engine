<?php

declare(strict_types=1);

namespace YiiPress\Update;

use JsonException;
use RuntimeException;

use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;
use function sprintf;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;

final class ReleaseClient
{
    private const string REPOSITORY = 'yiipress/engine';

    public function __construct(
        private readonly string $downloadBaseUrl = 'https://github.com/' . self::REPOSITORY . '/releases',
        private readonly string $apiUrl = 'https://api.github.com/repos/' . self::REPOSITORY . '/releases?per_page=100',
    ) {}

    /** @return array{version: string, package: string, checksums: string} */
    public function download(string $assetName, bool $nightly): array
    {
        $version = $nightly ? $this->latestNightlyTag($assetName) : 'latest';
        $releaseUrl = $version === 'latest'
            ? $this->downloadBaseUrl . '/latest/download'
            : $this->downloadBaseUrl . '/download/' . $version;

        return [
            'version' => $version,
            'package' => $this->get($releaseUrl . '/' . $assetName),
            'checksums' => $this->get($releaseUrl . '/SHA256SUMS'),
        ];
    }

    private function latestNightlyTag(string $assetName): string
    {
        try {
            $releases = json_decode($this->get($this->apiUrl), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('GitHub returned invalid release metadata.', previous: $e);
        }

        if (!is_array($releases)) {
            throw new RuntimeException('GitHub returned invalid release metadata.');
        }

        foreach ($releases as $release) {
            if (!is_array($release) || ($release['draft'] ?? true) || !($release['prerelease'] ?? false)) {
                continue;
            }
            $tag = $release['tag_name'] ?? null;
            $assets = $release['assets'] ?? [];
            if (!is_string($tag) || !str_starts_with($tag, 'nightly-') || !is_array($assets)) {
                continue;
            }
            $hasPackage = false;
            $hasChecksums = false;
            foreach ($assets as $asset) {
                if (!is_array($asset) || !is_string($asset['name'] ?? null)) {
                    continue;
                }
                $hasPackage = $hasPackage || $asset['name'] === $assetName;
                $hasChecksums = $hasChecksums || $asset['name'] === 'SHA256SUMS';
            }
            if ($hasPackage && $hasChecksums) {
                /** @var string $tag */
                return $tag;
            }
        }

        throw new RuntimeException(sprintf('Could not find a nightly release containing %s.', $assetName));
    }

    private function get(string $url): string
    {
        $context = stream_context_create(['http' => ['header' => "User-Agent: YiiPress self-update\r\n", 'timeout' => 30]]);
        $contents = @file_get_contents($url, false, $context);
        if ($contents === false) {
            throw new RuntimeException("Could not download $url.");
        }

        return $contents;
    }
}

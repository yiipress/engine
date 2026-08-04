<?php

declare(strict_types=1);

namespace YiiPress\Update;

use PharData;
use RuntimeException;

use function chmod;
use function dirname;
use function file_put_contents;
use function hash;
use function hash_equals;
use function preg_match;
use function preg_quote;
use function rename;
use function strtolower;
use function unlink;
use function sys_get_temp_dir;

final readonly class SelfUpdater
{
    public function __construct(
        private PackageLocator $packageLocator,
        private ReleaseClient $releaseClient,
    ) {}

    public function update(bool $nightly = false, ?Package $package = null): string
    {
        $package ??= $this->packageLocator->locate();
        $release = $this->releaseClient->download($package->assetName, $nightly);
        $expectedHash = $this->checksum($release['checksums'], $package->assetName);
        $actualHash = hash('sha256', $release['package']);
        if (!hash_equals($expectedHash, $actualHash)) {
            throw new RuntimeException("Checksum verification failed for {$package->assetName}.");
        }

        $contents = $this->packageContents($release['package'], $package, $actualHash);

        $temporaryPath = dirname($package->targetPath) . '/.yiipress-update-' . hash('xxh128', $package->targetPath . $actualHash);
        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false || !chmod($temporaryPath, 0755)) {
            @unlink($temporaryPath);
            throw new RuntimeException("Could not write the update next to {$package->targetPath}.");
        }
        if (!rename($temporaryPath, $package->targetPath)) {
            @unlink($temporaryPath);
            throw new RuntimeException("Could not replace {$package->targetPath}. Check its permissions.");
        }

        return $release['version'];
    }

    private function packageContents(string $download, Package $package, string $hash): string
    {
        if ($package->archiveMember === null) {
            return $download;
        }

        $archivePath = sys_get_temp_dir() . '/yiipress-update-' . $hash . '-' . $package->assetName;
        if (file_put_contents($archivePath, $download, LOCK_EX) === false) {
            throw new RuntimeException('Could not create a temporary update archive.');
        }

        try {
            $archive = new PharData($archivePath);
            $file = $archive[$package->archiveMember] ?? null;
            if (!$file instanceof \PharFileInfo) {
                throw new RuntimeException("{$package->assetName} does not contain {$package->archiveMember}.");
            }

            return $file->getContent();
        } finally {
            @unlink($archivePath);
        }
    }

    private function checksum(string $checksums, string $assetName): string
    {
        $quotedName = preg_quote($assetName, '/');
        if (preg_match('/^([a-f0-9]{64})\s+(?:\*|assets\/)?' . $quotedName . '$/mi', $checksums, $matches) !== 1) {
            throw new RuntimeException("SHA256SUMS does not contain {$assetName}.");
        }

        return strtolower($matches[1]);
    }
}

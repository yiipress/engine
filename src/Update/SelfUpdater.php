<?php

declare(strict_types=1);

namespace YiiPress\Update;

use PharData;
use RuntimeException;

use function chmod;
use function copy;
use function dirname;
use function hash;
use function hash_equals;
use function hash_file;
use function is_dir;
use function is_writable;
use function mkdir;
use function preg_match;
use function preg_quote;
use function strtolower;
use function unlink;
use function rmdir;
use function sys_get_temp_dir;

final readonly class SelfUpdater
{
    public function __construct(
        private PackageLocator $packageLocator,
        private ReleaseClient $releaseClient,
        private PackageReplacer $packageReplacer = new PackageReplacer(),
    ) {}

    public function update(bool $nightly = false, ?Package $package = null): string
    {
        $package ??= $this->packageLocator->locate();
        $targetDirectory = dirname($package->targetPath);
        if (!is_writable($targetDirectory)) {
            throw new RuntimeException(
                "Could not update {$package->targetPath}: installation directory $targetDirectory is not writable. "
                . 'Check its permissions or rerun with appropriate privileges.',
            );
        }

        $downloadPath = sys_get_temp_dir() . '/yiipress-download-' . hash('xxh128', $package->targetPath) . '-' . $package->assetName;
        $release = $this->releaseClient->download($package->assetName, $nightly, $downloadPath);
        $expectedHash = $this->checksum($release['checksums'], $package->assetName);
        $actualHash = hash_file('sha256', $downloadPath);
        if ($actualHash === false) {
            @unlink($downloadPath);
            throw new RuntimeException("Could not hash {$package->assetName}.");
        }
        if (!hash_equals($expectedHash, $actualHash)) {
            @unlink($downloadPath);
            throw new RuntimeException("Checksum verification failed for {$package->assetName}.");
        }

        $temporaryPath = dirname($package->targetPath) . '/.yiipress-update-' . hash('xxh128', $package->targetPath . $actualHash);
        try {
            $this->preparePackage($downloadPath, $temporaryPath, $package);
        } finally {
            @unlink($downloadPath);
        }
        if (!chmod($temporaryPath, 0755)) {
            @unlink($temporaryPath);
            throw new RuntimeException("Could not make the update executable next to {$package->targetPath}.");
        }
        $this->packageReplacer->replace($temporaryPath, $package->targetPath);

        return $release['version'];
    }

    private function preparePackage(string $downloadPath, string $temporaryPath, Package $package): void
    {
        if ($package->archiveMember === null) {
            if (!@copy($downloadPath, $temporaryPath)) {
                throw new RuntimeException("Could not write the update next to {$package->targetPath}.");
            }

            return;
        }

        $extractPath = sys_get_temp_dir() . '/yiipress-extract-' . hash('xxh128', $downloadPath);
        if (!is_dir($extractPath) && !mkdir($extractPath, 0700, true) && !is_dir($extractPath)) {
            throw new RuntimeException('Could not create a temporary extraction directory.');
        }

        try {
            $archive = new PharData($downloadPath);
            if (!$archive->extractTo($extractPath, $package->archiveMember, true)) {
                throw new RuntimeException("{$package->assetName} does not contain {$package->archiveMember}.");
            }
            if (!@copy($extractPath . '/' . $package->archiveMember, $temporaryPath)) {
                throw new RuntimeException("Could not write the update next to {$package->targetPath}.");
            }
        } finally {
            @unlink($extractPath . '/' . $package->archiveMember);
            @rmdir($extractPath);
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

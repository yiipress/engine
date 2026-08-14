<?php

declare(strict_types=1);

namespace YiiPress\Update;

use Phar;
use RuntimeException;

use function class_exists;
use function call_user_func;
use function function_exists;
use function is_file;
use function pathinfo;
use function strtolower;
use function php_uname;

use const PATHINFO_EXTENSION;

final readonly class PackageLocator
{
    private string $osFamily;
    private string $architecture;
    private string $staticBinaryPath;

    public function __construct(?string $osFamily = null, ?string $architecture = null, ?string $staticBinaryPath = null)
    {
        $this->osFamily = $osFamily ?? PHP_OS_FAMILY;
        $this->architecture = strtolower($architecture ?? php_uname('m'));
        $this->staticBinaryPath = $staticBinaryPath ?? $this->detectStaticBinaryPath();
    }

    public function locate(): Package
    {
        $targetPath = class_exists(Phar::class, false) ? Phar::running(false) : '';
        if ($targetPath === '') {
            $targetPath = $this->staticBinaryPath;
        }
        if ($targetPath === '' || !is_file($targetPath)) {
            throw new RuntimeException('Self-update is available only for PHAR and static binary installations.');
        }

        if (strtolower(pathinfo($targetPath, PATHINFO_EXTENSION)) === 'phar') {
            return new Package($targetPath, 'yiipress.phar');
        }

        return $this->locateBinary($targetPath);
    }

    public function locateBinary(string $targetPath): Package
    {
        $assetName = $this->binaryAssetName();

        return new Package($targetPath, $assetName, $this->osFamily === 'Windows' ? 'yiipress.exe' : 'yiipress');
    }

    private function binaryAssetName(): string
    {
        return match ($this->osFamily) {
            'Linux' => in_array($this->architecture, ['x86_64', 'amd64'], true) ? 'yiipress-linux-amd64.tar.gz' : throw new RuntimeException("Unsupported Linux architecture: {$this->architecture}."),
            'Darwin' => in_array($this->architecture, ['arm64', 'aarch64'], true) ? 'yiipress-macos-arm64.tar.gz' : throw new RuntimeException("Unsupported macOS architecture: {$this->architecture}."),
            'Windows' => in_array($this->architecture, ['x86_64', 'amd64'], true) ? 'yiipress-windows-amd64.zip' : throw new RuntimeException("Unsupported Windows architecture: {$this->architecture}."),
            default => throw new RuntimeException('Self-update is not available for this operating system.'),
        };
    }

    private function detectStaticBinaryPath(): string
    {
        if (!function_exists('micro_get_self_filename')) {
            return '';
        }

        $path = call_user_func(micro_get_self_filename(...));

        return is_string($path) ? $path : '';
    }
}

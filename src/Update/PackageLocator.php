<?php

declare(strict_types=1);

namespace YiiPress\Update;

use Phar;
use RuntimeException;

use function class_exists;
use function is_file;
use function pathinfo;
use function strtolower;

use const PATHINFO_EXTENSION;

final class PackageLocator
{
    public function locate(): Package
    {
        $targetPath = class_exists(Phar::class, false) ? Phar::running(false) : '';
        if ($targetPath === '' || !is_file($targetPath)) {
            throw new RuntimeException('Self-update is available only for PHAR and static binary installations.');
        }

        if (strtolower(pathinfo($targetPath, PATHINFO_EXTENSION)) === 'phar') {
            return new Package($targetPath, 'yiipress.phar');
        }

        return new Package($targetPath, $this->binaryAssetName());
    }

    private function binaryAssetName(): string
    {
        return match (PHP_OS_FAMILY) {
            'Linux' => PHP_INT_SIZE === 8 ? 'yiipress-linux-amd64' : throw new RuntimeException('Unsupported Linux architecture.'),
            'Darwin' => php_uname('m') === 'arm64' ? 'yiipress-macos-arm64' : throw new RuntimeException('Unsupported macOS architecture.'),
            'Windows' => PHP_INT_SIZE === 8 ? 'yiipress-windows-amd64.exe' : throw new RuntimeException('Unsupported Windows architecture.'),
            default => throw new RuntimeException('Self-update is not available for this operating system.'),
        };
    }
}

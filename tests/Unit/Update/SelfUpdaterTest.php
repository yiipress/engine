<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Update;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use YiiPress\Update\Package;
use YiiPress\Update\PackageLocator;
use YiiPress\Update\ReleaseClient;
use YiiPress\Update\SelfUpdater;

use function file_put_contents;
use function hash;
use function json_encode;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const JSON_THROW_ON_ERROR;

final class SelfUpdaterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/yiipress-self-update-' . uniqid('', true);
        mkdir($this->directory . '/releases/latest/download', recursive: true);
        mkdir($this->directory . '/releases/download/nightly-42', recursive: true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->directory);
    }

    #[Test]
    public function updatesStablePackageByDefault(): void
    {
        $this->createRelease('latest/download', 'stable-build');
        $target = $this->directory . '/yiipress.phar';
        file_put_contents($target, 'old-build');

        $version = $this->updater()->update(package: new Package($target, 'yiipress.phar'));

        self::assertSame('latest', $version);
        self::assertSame('stable-build', file_get_contents($target));
        self::assertSame(0755, fileperms($target) & 0777);
    }

    #[Test]
    public function resolvesAndUpdatesLatestNightlyPackage(): void
    {
        $this->createRelease('download/nightly-42', 'nightly-build');
        file_put_contents(
            $this->directory . '/releases.json',
            json_encode([[
                'tag_name' => 'nightly-42',
                'draft' => false,
                'prerelease' => true,
                'assets' => [['name' => 'yiipress.phar'], ['name' => 'SHA256SUMS']],
            ]], JSON_THROW_ON_ERROR),
        );
        $target = $this->directory . '/yiipress.phar';
        file_put_contents($target, 'old-build');

        $version = $this->updater()->update(true, new Package($target, 'yiipress.phar'));

        self::assertSame('nightly-42', $version);
        self::assertSame('nightly-build', file_get_contents($target));
    }

    #[Test]
    public function rejectsPackageWithInvalidChecksum(): void
    {
        $this->createRelease('latest/download', 'untrusted-build', str_repeat('0', 64));
        $target = $this->directory . '/yiipress.phar';
        file_put_contents($target, 'old-build');

        $this->expectExceptionMessage('Checksum verification failed');

        try {
            $this->updater()->update(package: new Package($target, 'yiipress.phar'));
        } finally {
            self::assertSame('old-build', file_get_contents($target));
        }
    }

    #[Test]
    public function packageLocatorRejectsSourceInstallation(): void
    {
        $this->expectExceptionMessage('only for PHAR and static binary installations');

        (new PackageLocator())->locate();
    }

    private function updater(): SelfUpdater
    {
        return new SelfUpdater(
            new PackageLocator(),
            new ReleaseClient(
                'file://' . $this->directory . '/releases',
                'file://' . $this->directory . '/releases.json',
            ),
        );
    }

    private function createRelease(string $path, string $contents, ?string $checksum = null): void
    {
        $directory = $this->directory . '/releases/' . $path;
        file_put_contents($directory . '/yiipress.phar', $contents);
        file_put_contents($directory . '/SHA256SUMS', ($checksum ?? hash('sha256', $contents)) . "  yiipress.phar\n");
    }
}

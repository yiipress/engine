<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Release;

use PHPUnit\Framework\TestCase;
use YiiPress\Extension\Archive\InstallLimits;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\GitHubRepository;
use YiiPress\Extension\Release\Release;
use YiiPress\Tests\Support\FakeHttpClient;

use function hash;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class GitHubReleaseSourceTest extends TestCase
{
    private const string ARCHIVE_URL = 'https://codeload.github.com/yiipress/theme-wonder/tar.gz/refs/tags/v1.0.0';

    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/yiipress-source-test-' . uniqid() . '.tar.gz';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testDownloadsArchiveWithChecksumAndSize(): void
    {
        $http = new FakeHttpClient()->route(self::ARCHIVE_URL, 'archive bytes');

        $archive = new GitHubReleaseSource($http)->downloadArchive(self::repository(), new Release('v1.0.0', '1.0.0'), $this->file);

        self::assertSame(self::ARCHIVE_URL, $archive->url);
        self::assertSame(hash('sha256', 'archive bytes'), $archive->sha256);
        self::assertSame(13, $archive->size);
        self::assertSame($this->file, $archive->file);
    }

    public function testRefusesArchiveOverDownloadLimit(): void
    {
        $http = new FakeHttpClient()->route(self::ARCHIVE_URL, '12345');
        $source = new GitHubReleaseSource($http, new InstallLimits(downloadBytes: 4));

        $this->expectExceptionObject(new InstallException(
            'download_too_large',
            self::ARCHIVE_URL . ' is larger than 4 bytes.',
        ));

        $source->downloadArchive(self::repository(), new Release('v1.0.0', '1.0.0'), $this->file);
    }

    public function testManifestOfTagWithoutOneIsNull(): void
    {
        $source = new GitHubReleaseSource(new FakeHttpClient());

        self::assertNull($source->manifest(self::repository(), new Release('v1.0.0', '1.0.0')));
    }

    public function testNormalisesTag(): void
    {
        self::assertSame('1.2.3', GitHubReleaseSource::release('v1.2.3')?->version);
        self::assertSame('1.2.3', GitHubReleaseSource::release('1.2.3')?->version);
        self::assertNull(GitHubReleaseSource::release('vv1.2.3'));
        self::assertNull(GitHubReleaseSource::release('v1.2.3-rc.1'));
        self::assertNull(GitHubReleaseSource::release('v1.2'));
    }

    private static function repository(): GitHubRepository
    {
        return GitHubRepository::fromUrl('https://github.com/yiipress/theme-wonder');
    }
}

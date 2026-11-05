<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Install;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallRequest;
use YiiPress\Extension\Install\PackageStager;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\GitHubRepository;
use YiiPress\Extension\Release\ReleaseSelector;
use YiiPress\Extension\Transaction\Transaction;
use YiiPress\Tests\Support\WonderFixture;

use function mkdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;

final class PackageStagerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-stager-test-' . uniqid();
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testStagesPackageWithoutChangingProject(): void
    {
        $source = new GitHubReleaseSource(WonderFixture::http());
        $request = $this->request();
        $selected = new ReleaseSelector($source)->select(
            $request->repository,
            $request->type,
            $request->version,
            $request->engine,
            $request->platform,
        );
        $transaction = Transaction::begin($this->root);

        try {
            $staged = new PackageStager($source)->stage($transaction, $request, $selected);

            self::assertSame(WonderFixture::ID, (string) $staged->manifest->id);
            self::assertSame($transaction->stagingPath('root/package'), $staged->path);
            self::assertEquals(WonderFixture::locked(), $staged->locked('themes/wonder', 'wonder'));
            self::assertSame(['.', '..', '.yiipress'], scandir($this->root));
        } finally {
            $transaction->discard();
        }
    }

    private function request(): InstallRequest
    {
        return new InstallRequest(
            $this->root,
            GitHubRepository::fromUrl('https://github.com/' . WonderFixture::REPOSITORY),
            ExtensionType::Theme,
            null,
            EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        );
    }
}

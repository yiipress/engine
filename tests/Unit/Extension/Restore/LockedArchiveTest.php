<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Restore;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Restore\LockedArchive;
use YiiPress\Extension\Restore\VerifiedPackage;
use YiiPress\Tests\Support\FakeHttpClient;
use YiiPress\Tests\Support\TarGzBuilder;
use YiiPress\Tests\Support\WonderFixture;

use function array_replace_recursive;
use function file_get_contents;
use function gzdecode;
use function gzencode;
use function json_decode;
use function json_encode;
use function mkdir;
use function str_repeat;
use function sys_get_temp_dir;
use function trim;
use function uniqid;

use const JSON_THROW_ON_ERROR;

final class LockedArchiveTest extends TestCase
{
    private string $staging;

    protected function setUp(): void
    {
        $this->staging = sys_get_temp_dir() . '/yiipress-locked-archive-test-' . uniqid();
        mkdir($this->staging, 0o755, true);
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->staging);
    }

    public function testExactArchiveGivesThePackage(): void
    {
        $package = $this->fetch(WonderFixture::http(), WonderFixture::locked());

        self::assertSame('<?php echo $content;', trim((string) file_get_contents($package->directory . '/entry.php')));
        self::assertFileExists($package->directory . '/assets/style.css');
        self::assertNull($package->warning);
    }

    public function testRegeneratedArchiveWithSameCommitAndFilesOnlyWarns(): void
    {
        $regenerated = (string) gzencode((string) gzdecode(WonderFixture::archive()), 1);

        $package = $this->fetch(WonderFixture::http($regenerated), WonderFixture::locked());

        self::assertNotNull($package->warning);
        self::assertStringContainsString(WonderFixture::locked()->archiveSha256, $package->warning);
        self::assertStringContainsString('commit and every file match', $package->warning);
        self::assertFileExists($package->directory . '/entry.php');
    }

    public function testMovedTagIsRefused(): void
    {
        $http = WonderFixture::http($this->archive(str_repeat('f', 40), '<?php echo $content;' . "\n"));

        $this->assertFails('archive_mismatch', $http, WonderFixture::locked());
    }

    public function testChangedFileUnderTheSameCommitIsRefused(): void
    {
        $http = WonderFixture::http($this->archive(WonderFixture::commit(), '<?php echo "changed";'));

        $exception = $this->assertFails('archive_mismatch', $http, WonderFixture::locked());

        self::assertStringEndsWith('differ from yiipress.lock: entry.php.', $exception->getMessage());
    }

    public function testManifestOfAnotherExtensionIsRefused(): void
    {
        $this->assertFails('archive_mismatch', WonderFixture::http(), WonderFixture::locked(), 'yiipress/other');
    }

    public function testMissingReleaseFailsWithoutLookingForAnother(): void
    {
        $http = new FakeHttpClient();

        $this->assertFails('release_not_found', $http, WonderFixture::locked());

        self::assertSame([WonderFixture::ARCHIVE_URL], $http->requests);
    }

    public function testIncompatiblePackageIsRefused(): void
    {
        $this->assertFails(
            'release_incompatible',
            WonderFixture::http(),
            WonderFixture::locked(),
            engine: EngineVersion::fromBuild('0.0.9'),
        );
    }

    public function testArchiveUrlMustBeTheTagArchiveOfTheSource(): void
    {
        $http = WonderFixture::http();
        $locked = self::with(WonderFixture::locked(), [
            'archive' => ['url' => 'https://codeload.github.com/evil/theme/tar.gz/refs/tags/v1.0.0'],
        ]);

        $this->assertFails('lock_invalid', $http, $locked);

        self::assertSame([], $http->requests);
    }

    public function testTagMustMatchTheVersion(): void
    {
        $locked = self::with(WonderFixture::locked(), ['version' => '1.0.1']);

        $exception = $this->assertFails('lock_invalid', WonderFixture::http(), $locked);

        self::assertStringContainsString('"extensions.yiipress/wonder.tag"', $exception->getMessage());
    }

    /**
     * @param array<string, mixed> $changes Lock fields to change.
     */
    public static function with(LockedExtension $locked, array $changes): LockedExtension
    {
        $data = json_decode(json_encode($locked->toArray(), JSON_THROW_ON_ERROR), true, 32, JSON_THROW_ON_ERROR);
        $json = json_encode(
            ['lock' => 1, 'extensions' => [WonderFixture::ID => array_replace_recursive($data, $changes)]],
            JSON_THROW_ON_ERROR,
        );

        return LockFile::fromJson($json)->extensions()[WonderFixture::ID];
    }

    private function archive(string $commit, string $entry): string
    {
        $prefix = 'theme-wonder-1.0.0';

        return TarGzBuilder::gitArchive($prefix, $commit)
            ->file($prefix . '/extension.yaml', WonderFixture::manifest())
            ->directory($prefix . '/package/')
            ->directory($prefix . '/package/assets/')
            ->file($prefix . '/package/assets/style.css', "body{}\n")
            ->file($prefix . '/package/entry.php', $entry)
            ->gzip();
    }

    private function fetch(
        FakeHttpClient $http,
        LockedExtension $locked,
        string $id = WonderFixture::ID,
        ?EngineVersion $engine = null,
    ): VerifiedPackage {
        return new LockedArchive(new GitHubReleaseSource($http))->fetch(
            $id,
            $locked,
            $this->staging,
            $engine ?? EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        );
    }

    private function assertFails(
        string $code,
        FakeHttpClient $http,
        LockedExtension $locked,
        string $id = WonderFixture::ID,
        ?EngineVersion $engine = null,
    ): InstallException {
        try {
            $this->fetch($http, $locked, $id, $engine);
        } catch (InstallException $exception) {
            self::assertSame($code, $exception->errorCode, $exception->getMessage());

            return $exception;
        }
        self::fail('No exception thrown.');
    }
}

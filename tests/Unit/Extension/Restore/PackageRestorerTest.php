<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Restore;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Manifest\ThemeFallback;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Restore\PackageRestorer;
use YiiPress\Extension\Restore\RestoredPackage;
use YiiPress\Extension\Restore\RestoreRefusedException;
use YiiPress\Extension\Restore\RestoreResult;
use YiiPress\Extension\Transaction\MutationLock;
use YiiPress\Tests\Support\FakeHttpClient;
use YiiPress\Tests\Support\WonderFixture;

use function array_map;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function gzdecode;
use function gzencode;
use function hash;
use function mkdir;
use function str_repeat;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class PackageRestorerTest extends TestCase
{
    private const string CONFIG = "title: Blog\ntheme: wonder\n";

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-restorer-test-' . uniqid();
        mkdir($this->root . '/content', 0o755, true);
        file_put_contents($this->root . '/content/config.yaml', self::CONFIG);
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testRestoresMissingPackageFromTheLockedArchiveOnly(): void
    {
        $lock = $this->writeLock([WonderFixture::ID => WonderFixture::locked()]);
        $http = WonderFixture::http();

        $result = $this->restore($http);

        self::assertSame([WonderFixture::ID => RestoredPackage::INSTALLED], $this->statuses($result));
        self::assertFileExists($this->root . '/themes/wonder/entry.php');
        self::assertFileExists($this->root . '/themes/wonder/assets/style.css');
        self::assertSame([WonderFixture::ARCHIVE_URL], $http->requests);
        self::assertSame($lock, file_get_contents($this->root . '/yiipress.lock'));
        self::assertSame(self::CONFIG, file_get_contents($this->root . '/content/config.yaml'));
        self::assertSame([], glob($this->root . '/.yiipress/staging/*'));
    }

    public function testIntactPackageIsSkippedWithoutDownloading(): void
    {
        $this->writeLock([WonderFixture::ID => WonderFixture::locked()]);
        $this->restore(WonderFixture::http());
        $http = new FakeHttpClient();

        $result = $this->restore($http);

        self::assertSame([WonderFixture::ID => RestoredPackage::INTACT], $this->statuses($result));
        self::assertSame([], $http->requests);
    }

    public function testRestoresOnlyMissingFiles(): void
    {
        $this->writeLock([WonderFixture::ID => WonderFixture::locked()]);
        $this->restore(WonderFixture::http());
        unlink($this->root . '/themes/wonder/assets/style.css');

        $result = $this->restore(WonderFixture::http());

        self::assertSame([WonderFixture::ID => RestoredPackage::REPAIRED], $this->statuses($result));
        self::assertSame(['assets/style.css'], $result->packages[0]->files);
        self::assertFileExists($this->root . '/themes/wonder/assets/style.css');
    }

    public function testRegeneratedArchiveWarnsAndRestores(): void
    {
        $this->writeLock([WonderFixture::ID => WonderFixture::locked()]);
        $regenerated = (string) gzencode((string) gzdecode(WonderFixture::archive()), 1);

        $result = $this->restore(WonderFixture::http($regenerated));

        self::assertNotNull($result->packages[0]->warning);
        self::assertFileExists($this->root . '/themes/wonder/entry.php');
    }

    public function testLocalProblemsOfEveryPackageAreReportedBeforeAnyDownload(): void
    {
        $this->writeLock([WonderFixture::ID => WonderFixture::locked(), 'acme/blue' => self::blue()]);
        mkdir($this->root . '/themes/blue', 0o755, true);
        file_put_contents($this->root . '/themes/blue/entry.php', 'edited');
        file_put_contents($this->root . '/themes/blue/notes.txt', 'mine');
        $http = WonderFixture::http();

        $codes = $this->refusedCodes($http);

        self::assertSame(['files_modified', 'files_unexpected'], $codes);
        self::assertSame([], $http->requests);
        self::assertFileDoesNotExist($this->root . '/themes/wonder');
        self::assertSame('edited', file_get_contents($this->root . '/themes/blue/entry.php'));
        self::assertSame('mine', file_get_contents($this->root . '/themes/blue/notes.txt'));
    }

    public function testDestinationTakenByAnotherEntryIsRefused(): void
    {
        mkdir($this->root . '/themes/Wonder', 0o755, true);
        $this->writeLock([WonderFixture::ID => WonderFixture::locked()]);

        self::assertSame(['destination_exists'], $this->refusedCodes(WonderFixture::http()));
    }

    public function testTwoEntriesWithOneDestinationAreRefused(): void
    {
        $this->writeLock([
            WonderFixture::ID => WonderFixture::locked(),
            'acme/wonder' => self::blue('themes/wonder', 'wonder'),
        ]);

        self::assertSame(['name_collision'], $this->refusedCodes(WonderFixture::http()));
    }

    public function testDestinationOutsideThemesIsRefused(): void
    {
        $locked = LockedArchiveTest::with(WonderFixture::locked(), ['destination' => 'content']);
        $this->writeLock([WonderFixture::ID => $locked]);

        self::assertSame(['lock_invalid'], $this->refusedCodes(WonderFixture::http()));
    }

    public function testArchiveProblemLeavesEveryPackageUnrepaired(): void
    {
        $this->writeLock([WonderFixture::ID => WonderFixture::locked(), 'acme/blue' => self::blue()]);
        $http = WonderFixture::http();

        $codes = $this->refusedCodes($http);

        self::assertSame(['release_not_found'], $codes);
        self::assertFileDoesNotExist($this->root . '/themes/wonder');
        self::assertFileDoesNotExist($this->root . '/themes/blue');
        self::assertSame([], glob($this->root . '/.yiipress/staging/*'));
        self::assertFileDoesNotExist($this->root . '/.yiipress/journal.json');
    }

    public function testNetworkFailureStopsAtOnce(): void
    {
        $this->writeLock([WonderFixture::ID => WonderFixture::locked()]);
        $http = new FakeHttpClient()->route(WonderFixture::ARCHIVE_URL, 'Unavailable', 503);

        try {
            $this->restore($http);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('network_failed', $exception->errorCode);
        }

        self::assertFileDoesNotExist($this->root . '/themes/wonder');
    }

    public function testProjectWithoutLockHasNothingToRestore(): void
    {
        $http = new FakeHttpClient();

        $result = $this->restore($http);

        self::assertSame([], $result->packages);
        self::assertFileDoesNotExist($this->root . '/yiipress.lock');
    }

    public function testRefusesWhileAnotherCommandHoldsTheLock(): void
    {
        $this->writeLock([WonderFixture::ID => WonderFixture::locked()]);
        $lock = MutationLock::acquire($this->root);
        try {
            $this->restore(WonderFixture::http());
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('mutation_locked', $exception->errorCode);
        } finally {
            $lock->release();
        }

        self::assertFileDoesNotExist($this->root . '/themes/wonder');
    }

    public function testRecoversInterruptedOperationFirst(): void
    {
        $this->writeLock([WonderFixture::ID => WonderFixture::locked()]);
        mkdir($this->root . '/.yiipress');
        file_put_contents(
            $this->root . '/.yiipress/journal.json',
            '{"journal": 1, "tx": "0123456789abcdef", "operation": "install", "state": "committed", "steps": []}',
        );

        $result = $this->restore(WonderFixture::http());

        self::assertNotNull($result->recovered);
        self::assertFileExists($this->root . '/themes/wonder/entry.php');
    }

    /**
     * @param array<string, LockedExtension> $extensions
     * @return string The lock file bytes.
     */
    private function writeLock(array $extensions): string
    {
        $lock = LockFile::empty();
        foreach ($extensions as $id => $extension) {
            $lock = $lock->withExtension(ExtensionId::tryFromString($id) ?? self::fail("Bad ID $id."), $extension);
        }
        file_put_contents($this->root . '/yiipress.lock', $lock->toJson());

        return $lock->toJson();
    }

    private function restore(FakeHttpClient $http): RestoreResult
    {
        return new PackageRestorer(new GitHubReleaseSource($http))->restore(
            $this->root,
            EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        );
    }

    /**
     * @return list<string>
     */
    private function refusedCodes(FakeHttpClient $http): array
    {
        try {
            $this->restore($http);
        } catch (RestoreRefusedException $exception) {
            return array_map(static fn(InstallException $problem): string => $problem->errorCode, $exception->problems);
        }
        self::fail('Restore was not refused.');
    }

    /**
     * @return array<string, string>
     */
    private function statuses(RestoreResult $result): array
    {
        $statuses = [];
        foreach ($result->packages as $package) {
            $statuses[$package->id] = $package->status;
        }

        return $statuses;
    }

    private static function blue(string $destination = 'themes/blue', string $name = 'blue'): LockedExtension
    {
        return new LockedExtension(
            type: WonderFixture::locked()->type,
            source: 'https://github.com/acme/theme-blue',
            version: '2.0.0',
            tag: 'v2.0.0',
            commit: str_repeat('a', 40),
            archiveKind: LockedExtension::ARCHIVE_GITHUB_TARBALL,
            archiveUrl: 'https://codeload.github.com/acme/theme-blue/tar.gz/refs/tags/v2.0.0',
            archiveSha256: str_repeat('b', 64),
            archiveSize: 100,
            destination: $destination,
            name: $name,
            fallback: ThemeFallback::Minimal,
            manifestSha256: str_repeat('c', 64),
            files: ['entry.php' => hash('sha256', '<?php')],
        );
    }
}

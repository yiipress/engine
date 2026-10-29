<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use LogicException;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\DirectoryHashes;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Http\HttpClientInterface;
use YiiPress\Extension\Http\HttpResponse;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Restore\InstalledPackage;
use YiiPress\Extension\Restore\PackageRestorer;

use function file_put_contents;
use function mkdir;
use function sprintf;
use function str_repeat;
use function sys_get_temp_dir;
use function uniqid;

/**
 * `restore:extensions` in a project whose packages are all in place: it reads the lock and hashes every installed
 * file, and must not download anything. Five themes of 200 files of 2 KiB each.
 */
#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class RestoreExtensionsBench
{
    private const int THEMES = 5;
    private const int FILES = 200;

    private string $root;
    private LockedExtension $first;

    public function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-restore-bench-' . uniqid();
        $lock = LockFile::empty();
        for ($theme = 0; $theme < self::THEMES; $theme++) {
            $name = 'theme' . $theme;
            $directory = $this->root . '/themes/' . $name;
            mkdir($directory . '/assets', 0o755, true);
            for ($file = 0; $file < self::FILES; $file++) {
                $path = $directory . ($file % 2 === 0 ? '/assets/' : '/') . 'file' . $file . '.php';
                file_put_contents($path, str_repeat((string) ($file % 10), 2048));
            }
            $locked = self::locked($name, DirectoryHashes::of($directory));
            $this->first ??= $locked;
            $id = ExtensionId::tryFromString('bench/' . $name) ?? throw new LogicException('Invalid ID.');
            $lock = $lock->withExtension($id, $locked);
        }
        file_put_contents($this->root . '/' . LockFile::FILE_NAME, $lock->toJson());
    }

    public function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    #[Revs(10)]
    #[Iterations(5)]
    #[Warmup(1)]
    public function benchInspectOnePackage(): void
    {
        InstalledPackage::inspect($this->root, $this->first);
    }

    #[Revs(10)]
    #[Iterations(5)]
    #[Warmup(1)]
    public function benchRestoreIntactProject(): void
    {
        new PackageRestorer(new GitHubReleaseSource($this->noNetwork()))->restore(
            $this->root,
            EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        );
    }

    /**
     * Fails the benchmark if restoring intact packages tries to download.
     */
    private function noNetwork(): HttpClientInterface
    {
        return new class implements HttpClientInterface {
            public function get(string $url, string $destination, int $maxBytes): HttpResponse
            {
                throw new LogicException(sprintf('Intact packages must not be downloaded; %s was requested.', $url));
            }
        };
    }

    /**
     * @param array<string, string> $files
     */
    private static function locked(string $name, array $files): LockedExtension
    {
        return new LockedExtension(
            type: ExtensionType::Theme,
            source: 'https://github.com/bench/' . $name,
            version: '1.0.0',
            tag: 'v1.0.0',
            commit: str_repeat('a', 40),
            archiveKind: LockedExtension::ARCHIVE_GITHUB_TARBALL,
            archiveUrl: 'https://codeload.github.com/bench/' . $name . '/tar.gz/refs/tags/v1.0.0',
            archiveSha256: str_repeat('b', 64),
            archiveSize: 1,
            destination: 'themes/' . $name,
            name: $name,
            manifestSha256: str_repeat('c', 64),
            files: $files,
        );
    }
}

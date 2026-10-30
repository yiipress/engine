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
use YiiPress\Extension\Archive\InstallLimits;
use YiiPress\Extension\Archive\TarGzReader;
use YiiPress\Extension\DirectoryHashes;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Tests\Support\TarGzBuilder;

use function hash;
use function intdiv;
use function mkdir;
use function random_bytes;
use function sprintf;
use function str_repeat;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Hot paths of `get:theme` that do not touch the network: reading the release archive, hashing the installed files
 * and writing and reading `yiipress.lock`.
 */
#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
#[Iterations(5)]
#[Warmup(1)]
final class ExtensionInstallBench
{
    /** Files in the theme package: templates, partials, translations, assets. */
    private const int PACKAGE_FILES = 300;
    private const int FILE_BYTES = 4096;
    /** Extensions in the lock file, each with {@see PACKAGE_FILES} file hashes. */
    private const int LOCKED_EXTENSIONS = 20;

    private string $baseDir;
    private string $archive;
    private string $packageDir;
    private int $extraction = 0;
    private TarGzReader $reader;
    private InstallLimits $limits;
    private LockFile $lock;
    private string $lockJson;

    public function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/yiipress-extension-install-bench-' . uniqid();
        mkdir($this->baseDir, 0o755, true);
        $this->reader = new TarGzReader();
        $this->limits = new InstallLimits();

        $builder = TarGzBuilder::gitArchive('theme-wonder-1.0.0')
            ->file('theme-wonder-1.0.0/extension.yaml', "manifest: 1\n")
            ->directory('theme-wonder-1.0.0/package/');
        foreach (['partials', 'assets', 'assets/css', 'assets/img'] as $directory) {
            $builder->directory('theme-wonder-1.0.0/package/' . $directory . '/');
        }
        foreach (self::packagePaths() as $path) {
            // Half random bytes, so gzip compresses the archive about as much as real themes.
            $contents = random_bytes(self::FILE_BYTES / 2) . str_repeat('<p>', intdiv(self::FILE_BYTES, 6));
            $builder->file('theme-wonder-1.0.0/package/' . $path, $contents);
        }
        $this->archive = $builder->writeTo($this->baseDir . '/archive.tar.gz');
        $this->packageDir = $this->reader->extract($this->archive, $this->baseDir . '/extracted', $this->limits)->root
            . '/package';

        $files = DirectoryHashes::of($this->packageDir);
        $lock = LockFile::empty();
        for ($i = 0; $i < self::LOCKED_EXTENSIONS; $i++) {
            $name = sprintf('theme-%02d', $i);
            $lock = $lock->withExtension(
                ExtensionId::tryFromString('vendor/' . $name) ?? throw new LogicException('Invalid ID.'),
                new LockedExtension(
                    ExtensionType::Theme,
                    'https://github.com/vendor/' . $name,
                    '1.0.0',
                    'v1.0.0',
                    str_repeat('a', 40),
                    LockedExtension::ARCHIVE_GITHUB_TARBALL,
                    'https://codeload.github.com/vendor/' . $name . '/tar.gz/refs/tags/v1.0.0',
                    hash('sha256', $name),
                    123456,
                    'themes/' . $name,
                    $name,
                    hash('sha256', $name . '.yaml'),
                    $files,
                ),
            );
        }
        $this->lock = $lock;
        $this->lockJson = $lock->toJson();
    }

    public function tearDown(): void
    {
        DirectoryRemover::remove($this->baseDir);
    }

    #[Revs(10)]
    public function benchExtractThemeArchive(): void
    {
        $this->reader->extract($this->archive, $this->baseDir . '/run-' . $this->extraction++, $this->limits);
    }

    #[Revs(20)]
    public function benchHashPackageFiles(): void
    {
        DirectoryHashes::of($this->packageDir);
    }

    #[Revs(50)]
    public function benchLockToJson(): void
    {
        $this->lock->toJson();
    }

    #[Revs(50)]
    public function benchLockFromJson(): void
    {
        LockFile::fromJson($this->lockJson);
    }

    /**
     * @return list<string>
     */
    private static function packagePaths(): array
    {
        $paths = [];
        for ($i = 0; $i < self::PACKAGE_FILES; $i++) {
            $paths[] = match ($i % 4) {
                0 => sprintf('template-%03d.php', $i),
                1 => sprintf('partials/partial-%03d.php', $i),
                2 => sprintf('assets/css/style-%03d.css', $i),
                default => sprintf('assets/img/icon-%03d.svg', $i),
            };
        }

        return $paths;
    }
}

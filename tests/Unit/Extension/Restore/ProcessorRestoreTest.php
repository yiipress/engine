<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Restore;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Install\InstallRequest;
use YiiPress\Extension\Install\PackageInstaller;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Processor\ProcessorDestinationPolicy;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\GitHubRepository;
use YiiPress\Extension\Restore\PackageRestorer;
use YiiPress\Extension\Restore\RestoredPackage;
use YiiPress\Extension\Restore\RestoreRefusedException;
use YiiPress\Extension\Restore\RestoreResult;
use YiiPress\Tests\Support\FakeHttpClient;
use YiiPress\Tests\Support\ProcessorFixture;

use function array_map;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function str_replace;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Processors restore into the destination their lock entry records, in whatever content directory that is.
 */
final class ProcessorRestoreTest extends TestCase
{
    private const string CONFIG = "title: Blog\nlanguages: [en]\nprocessors: [extensions/processors/acme/toc/toc.php]\n";
    private const array FILES = ['toc.php' => "<?php\nreturn require __DIR__ . '/lib/mark.php';\n", 'lib/mark.php' => '<?php'];

    private string $root;
    private FakeHttpClient $http;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-processor-restore-test-' . uniqid();
        mkdir($this->root . '/site', 0o755, true);
        file_put_contents($this->root . '/site/config.yaml', self::CONFIG);
        $this->http = ProcessorFixture::http(['v1.0.0' => ProcessorFixture::release('1.0.0', self::FILES)]);
        new PackageInstaller(new GitHubReleaseSource($this->http))->install(
            new InstallRequest(
                $this->root,
                GitHubRepository::fromUrl('https://github.com/acme/processor-toc'),
                ExtensionType::Processor,
                null,
                EngineVersion::fromBuild('0.1.13'),
                new Platform('linux', 'amd64'),
            ),
            new ProcessorDestinationPolicy('site'),
        );
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testRestoresMissingPackageIntoItsContentDirectory(): void
    {
        $lock = file_get_contents($this->root . '/yiipress.lock');
        DirectoryRemover::remove($this->root . '/site/extensions');

        $result = $this->restore();

        self::assertSame([RestoredPackage::INSTALLED], $this->statuses($result));
        self::assertSame(self::FILES['lib/mark.php'], file_get_contents($this->root . '/site/extensions/processors/acme/toc/lib/mark.php'));
        self::assertSame($lock, file_get_contents($this->root . '/yiipress.lock'));
        self::assertSame(self::CONFIG, file_get_contents($this->root . '/site/config.yaml'));
    }

    public function testRepairsMissingSupportFile(): void
    {
        unlink($this->root . '/site/extensions/processors/acme/toc/lib/mark.php');

        $result = $this->restore();

        self::assertSame([RestoredPackage::REPAIRED], $this->statuses($result));
        self::assertFileExists($this->root . '/site/extensions/processors/acme/toc/lib/mark.php');
    }

    public function testIntactPackageIsSkipped(): void
    {
        self::assertSame([RestoredPackage::INTACT], $this->statuses($this->restore()));
    }

    public function testRefusesDestinationOutsideTheProcessorRule(): void
    {
        $lock = (string) file_get_contents($this->root . '/yiipress.lock');
        file_put_contents(
            $this->root . '/yiipress.lock',
            str_replace('site/extensions/processors/acme/toc', 'site/processors', $lock),
        );

        try {
            $this->restore();
            self::fail('Restore was not refused.');
        } catch (RestoreRefusedException $exception) {
            self::assertSame(
                ['lock_invalid'],
                array_map(static fn(InstallException $problem): string => $problem->errorCode, $exception->problems),
            );
        }
    }

    private function restore(): RestoreResult
    {
        return new PackageRestorer(new GitHubReleaseSource($this->http))->restore(
            $this->root,
            EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        );
    }

    /**
     * @return list<string>
     */
    private function statuses(RestoreResult $result): array
    {
        return array_map(static fn(RestoredPackage $package): string => $package->status, $result->packages);
    }
}

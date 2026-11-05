<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Lifecycle;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Install\InstallRequest;
use YiiPress\Extension\Install\PackageInstaller;
use YiiPress\Extension\Lifecycle\ExtensionUpdater;
use YiiPress\Extension\Lifecycle\UpdateResult;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Processor\ProcessorDestinationPolicy;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\GitHubRepository;
use YiiPress\Tests\Support\FakeHttpClient;
use YiiPress\Tests\Support\ProcessorFixture;

use function file_get_contents;
use function file_put_contents;
use function glob;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const GLOB_BRACE;

final class ProcessorUpdaterTest extends TestCase
{
    private const string CONFIG = "title: Blog\nlanguages: [en]\n";
    private const string RENAMED = "    - path: src/toc.php\n      stages: [before_markdown]\n";
    private const string BEFORE_ONLY = "    - path: toc.php\n      stages: [before_markdown]\n";
    private const string BOTH_STAGES = "    - path: toc.php\n      stages: [before_markdown, after_markdown]\n";

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-processor-updater-test-' . uniqid();
        mkdir($this->root . '/content', 0o755, true);
        mkdir($this->root . '/site', 0o755, true);
        file_put_contents($this->root . '/content/config.yaml', self::CONFIG);
        file_put_contents($this->root . '/site/config.yaml', self::CONFIG);
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testDisabledProcessorTakesAnyRelease(): void
    {
        $this->install(self::BOTH_STAGES);

        $result = $this->update(self::RENAMED);

        self::assertTrue($result->updated());
        self::assertSame('1.1.0', $result->current->version);
        self::assertFileExists($this->root . '/content/extensions/processors/acme/toc/src/toc.php');
        self::assertFileDoesNotExist($this->root . '/content/extensions/processors/acme/toc/toc.php');
    }

    public function testEnabledEntryPointThatStaysKeepsItsConfiguration(): void
    {
        $this->install(self::BEFORE_ONLY);
        $config = self::CONFIG . "processors: [extensions/processors/acme/toc/toc.php]\n";
        file_put_contents($this->root . '/content/config.yaml', $config);

        $result = $this->update(self::BOTH_STAGES);

        self::assertSame('1.1.0', $result->current->version);
        self::assertSame("<?php // 1.1.0\n", file_get_contents($this->root . '/content/extensions/processors/acme/toc/toc.php'));
        self::assertSame($config, file_get_contents($this->root . '/content/config.yaml'));
    }

    public function testRefusesReleaseWithoutEnabledEntryPoint(): void
    {
        $this->install(self::BEFORE_ONLY);
        file_put_contents(
            $this->root . '/content/config.yaml',
            self::CONFIG . "processors:\n  feed:\n    before_markdown: [extensions/processors/acme/toc/toc.php]\n",
        );

        $exception = $this->updateFails(self::RENAMED);

        self::assertSame(
            'Release 1.1.0 of processor acme/toc does not declare what content/config.yaml enables: '
            . 'feed.before_markdown: extensions/processors/acme/toc/toc.php '
            . '(no entry point "toc.php" for before_markdown).',
            $exception->getMessage(),
        );
    }

    public function testRefusesReleaseThatDropsEnabledStage(): void
    {
        $this->install(self::BOTH_STAGES);
        file_put_contents(
            $this->root . '/content/config.yaml',
            self::CONFIG . "processors:\n  content:\n    after_markdown: [extensions/processors/acme/toc/toc.php]\n",
        );

        $exception = $this->updateFails(self::BEFORE_ONLY);

        self::assertStringContainsString('(no entry point "toc.php" for after_markdown)', $exception->getMessage());
    }

    public function testChecksConfigOfTheContentDirectoryItWasInstalledInto(): void
    {
        $this->install(self::BEFORE_ONLY, 'site');
        file_put_contents($this->root . '/site/config.yaml', self::CONFIG . "processors: [extensions/processors/acme/toc/toc.php]\n");

        $exception = $this->updateFails(self::RENAMED);

        self::assertStringContainsString('site/config.yaml enables', $exception->getMessage());
    }

    public function testRefusesLocalChanges(): void
    {
        $this->install(self::BEFORE_ONLY);
        file_put_contents($this->root . '/content/extensions/processors/acme/toc/notes.txt', 'mine');

        $this->updateFails(self::BEFORE_ONLY, 'local_changes');
    }

    private function install(string $entryPoints, string $contentDir = 'content'): void
    {
        new PackageInstaller(new GitHubReleaseSource($this->http($entryPoints)))->install(
            new InstallRequest(
                $this->root,
                GitHubRepository::fromUrl('https://github.com/acme/processor-toc'),
                ExtensionType::Processor,
                '1.0.0',
                EngineVersion::fromBuild('0.1.13'),
                new Platform('linux', 'amd64'),
            ),
            new ProcessorDestinationPolicy($contentDir),
        );
    }

    /**
     * Release 1.0.0 with the given entry points; 1.1.0 with the next ones, and its files named after them.
     */
    private function http(string $entryPoints, ?string $next = null): FakeHttpClient
    {
        $releases = ['v1.0.0' => ProcessorFixture::release('1.0.0', ['toc.php' => "<?php // 1.0.0\n"], $entryPoints)];
        if ($next !== null) {
            $file = $next === self::RENAMED ? 'src/toc.php' : 'toc.php';
            $releases['v1.1.0'] = ProcessorFixture::release('1.1.0', [$file => "<?php // 1.1.0\n"], $next);
        }

        return ProcessorFixture::http($releases);
    }

    private function update(string $next): UpdateResult
    {
        $id = ExtensionId::tryFromString('acme/toc');
        self::assertNotNull($id);

        return ExtensionUpdater::forProcessor(new GitHubReleaseSource($this->http(self::BEFORE_ONLY, $next)))->update(
            $this->root,
            $id,
            null,
            EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        );
    }

    private function updateFails(string $next, string $code = 'entry_point_in_use'): InstallException
    {
        $lock = file_get_contents($this->root . '/yiipress.lock');
        $destination = LockFile::read($this->root . '/yiipress.lock')->extensions()['acme/toc']->destination;
        $before = file_get_contents($this->root . '/' . $destination . '/toc.php');
        try {
            $this->update($next);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame($code, $exception->errorCode, $exception->getMessage());
            self::assertSame($lock, file_get_contents($this->root . '/yiipress.lock'));
            self::assertSame($before, file_get_contents($this->root . '/' . $destination . '/toc.php'));
            self::assertSame([], glob($this->root . '/.yiipress/{staging,backup}/*', GLOB_BRACE));

            return $exception;
        }
    }
}

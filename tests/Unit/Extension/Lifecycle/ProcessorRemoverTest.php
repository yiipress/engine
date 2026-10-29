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
use YiiPress\Extension\Lifecycle\ExtensionRemover;
use YiiPress\Extension\Lifecycle\RemoveResult;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Processor\ProcessorDestinationPolicy;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\GitHubRepository;
use YiiPress\Tests\Support\ProcessorFixture;

use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function str_replace;
use function sys_get_temp_dir;
use function uniqid;

use const GLOB_BRACE;

final class ProcessorRemoverTest extends TestCase
{
    private const string CONFIG = "title: Blog\nlanguages: [en]\n";
    private const string PACKAGE = 'content/extensions/processors/acme/toc';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-processor-remover-test-' . uniqid();
        mkdir($this->root . '/content', 0o755, true);
        mkdir($this->root . '/site', 0o755, true);
        file_put_contents($this->root . '/content/config.yaml', self::CONFIG);
        file_put_contents($this->root . '/site/config.yaml', self::CONFIG);
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testRemovesDisabledProcessor(): void
    {
        $this->install();

        $result = $this->remove();

        self::assertTrue($result->deletedDirectory);
        self::assertFalse(is_dir($this->root . '/' . self::PACKAGE));
        self::assertNull(LockFile::read($this->root . '/yiipress.lock')->get($this->id()));
        self::assertSame(self::CONFIG, file_get_contents($this->root . '/content/config.yaml'));
        self::assertSame([], glob($this->root . '/.yiipress/{staging,backup}/*', GLOB_BRACE));
    }

    public function testRefusesEnabledProcessor(): void
    {
        $this->install();
        file_put_contents(
            $this->root . '/content/config.yaml',
            self::CONFIG . "processors:\n  content:\n    after_markdown: [extensions/processors/acme/toc/toc.php]\n",
        );

        $exception = $this->removeFails('extension_in_use');

        self::assertStringContainsString(
            'is still enabled in content/config.yaml: content.after_markdown: extensions/processors/acme/toc/toc.php.',
            $exception->getMessage(),
        );
        self::assertStringContainsString('Remove these paths from "processors" in content/config.yaml', (string) $exception->getSolution());
    }

    public function testReadsConfigOfTheContentDirectoryItWasInstalledInto(): void
    {
        $this->install('site');
        $enabled = self::CONFIG . "processors: [extensions/processors/acme/toc/toc.php]\n";
        file_put_contents($this->root . '/site/config.yaml', $enabled);

        $exception = $this->removeFails('extension_in_use', 'site/config.yaml');

        self::assertStringContainsString('site/config.yaml', $exception->getMessage());
    }

    public function testRefusesLocalChanges(): void
    {
        $this->install();
        file_put_contents($this->root . '/' . self::PACKAGE . '/toc.php', '<?php // mine');

        $exception = $this->removeFails('local_changes');

        self::assertStringStartsWith('Processor acme/toc 1.0.0 in "' . self::PACKAGE . '" has local changes', $exception->getMessage());
    }

    public function testDropsLockEntryWhenDirectoryIsGone(): void
    {
        $this->install();
        DirectoryRemover::remove($this->root . '/' . self::PACKAGE);

        self::assertFalse($this->remove()->deletedDirectory);
        self::assertNull(LockFile::read($this->root . '/yiipress.lock')->get($this->id()));
    }

    public function testRefusesUntrustedDestination(): void
    {
        $this->install();
        $lock = (string) file_get_contents($this->root . '/yiipress.lock');
        file_put_contents($this->root . '/yiipress.lock', str_replace(self::PACKAGE, 'content/processors', $lock));

        $this->removeFails('lock_invalid');

        self::assertFileExists($this->root . '/' . self::PACKAGE . '/toc.php');
    }

    public function testRefusesNotInstalledAndOtherTypes(): void
    {
        $this->removeFails('not_installed');

        $theme = ExtensionRemover::forTheme('content');
        $this->install();
        try {
            $theme->remove($this->root, $this->id());
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('extension_not_theme', $exception->errorCode);
        }
    }

    private function install(string $contentDir = 'content'): void
    {
        $http = ProcessorFixture::http(['v1.0.0' => ProcessorFixture::release('1.0.0', ['toc.php' => '<?php'])]);
        new PackageInstaller(new GitHubReleaseSource($http))->install(
            new InstallRequest(
                $this->root,
                GitHubRepository::fromUrl('https://github.com/acme/processor-toc'),
                ExtensionType::Processor,
                null,
                EngineVersion::fromBuild('0.1.13'),
                new Platform('linux', 'amd64'),
            ),
            new ProcessorDestinationPolicy($contentDir),
        );
    }

    private function id(): ExtensionId
    {
        $id = ExtensionId::tryFromString('acme/toc');
        self::assertNotNull($id);

        return $id;
    }

    private function remove(): RemoveResult
    {
        return ExtensionRemover::forProcessor()->remove($this->root, $this->id());
    }

    private function removeFails(string $code, string $config = 'content/config.yaml'): InstallException
    {
        $lock = @file_get_contents($this->root . '/yiipress.lock');
        $configContents = file_get_contents($this->root . '/' . $config);
        try {
            $this->remove();
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame($code, $exception->errorCode, $exception->getMessage());
            self::assertSame($lock, @file_get_contents($this->root . '/yiipress.lock'));
            self::assertSame($configContents, file_get_contents($this->root . '/' . $config));
            self::assertSame([], glob($this->root . '/.yiipress/{staging,backup}/*', GLOB_BRACE));

            return $exception;
        }
    }
}

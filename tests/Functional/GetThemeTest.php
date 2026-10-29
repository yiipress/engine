<?php

declare(strict_types=1);

namespace YiiPress\Tests\Functional;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Tester\CommandTester;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Console\BuildCommand;
use YiiPress\Console\GetThemeCommand;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\Platform;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Render\MarkdownRenderer;
use YiiPress\Tests\Support\FakeHttpClient;
use Yiisoft\Yii\Console\ExitCode;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function mkdir;
use function sort;
use function strlen;
use function substr;
use function sys_get_temp_dir;
use function trim;
use function uniqid;

/**
 * `get:theme` installs a theme release served from a local fixture (the archive is made by `git archive`), then
 * `build` renders the site with it.
 */
final class GetThemeTest extends TestCase
{
    private const string REPOSITORY = 'yiipress/theme-wonder';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-get-theme-e2e-' . uniqid();
        mkdir($this->root . '/content', 0o755, true);
        file_put_contents(
            $this->root . '/content/config.yaml',
            "title: Wonder Site\nbase_url: https://example.com\nlanguages: [en]\n",
        );
        file_put_contents(
            $this->root . '/content/index.md',
            "---\ntitle: Home\npermalink: /\n---\n\nHello from the site.\n",
        );
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testInstalledThemeIsActivatedAndUsedByBuild(): void
    {
        $get = $this->getTheme(['--url' => 'https://github.com/' . self::REPOSITORY]);

        self::assertSame(ExitCode::OK, $get->getStatusCode(), $get->getDisplay());
        self::assertStringEndsWith(
            "theme: wonder\n",
            (string) file_get_contents($this->root . '/content/config.yaml'),
        );

        $build = $this->build('output');

        self::assertSame(ExitCode::OK, $build->getStatusCode(), $build->getDisplay());
        self::assertSame(
            '<p>Hello from the site.</p>',
            trim((string) file_get_contents($this->root . '/output/index.html')),
        );
    }

    public function testThemeInstalledWithoutActivationDoesNotChangeTheSite(): void
    {
        $this->build('before');

        $get = $this->getTheme(['--url' => 'https://github.com/' . self::REPOSITORY, '--no-activate' => true]);
        self::assertSame(ExitCode::OK, $get->getStatusCode(), $get->getDisplay());
        $build = $this->build('after');

        self::assertSame(ExitCode::OK, $build->getStatusCode(), $build->getDisplay());
        self::assertFileExists($this->root . '/themes/wonder/entry.php');
        self::assertSame($this->files('before'), $this->files('after'));
        foreach ($this->files('before') as $file) {
            self::assertSame(
                file_get_contents($this->root . '/before/' . $file),
                file_get_contents($this->root . '/after/' . $file),
                $file,
            );
        }
    }

    public function testThemeUsedOnlyByAnEntryIsAvailable(): void
    {
        file_put_contents(
            $this->root . '/content/special.md',
            "---\ntitle: Special\npermalink: /special/\ntheme: wonder\n---\n\nSpecial page.\n",
        );
        $get = $this->getTheme(['--url' => 'https://github.com/' . self::REPOSITORY, '--no-activate' => true]);
        self::assertSame(ExitCode::OK, $get->getStatusCode(), $get->getDisplay());

        $build = $this->build('output');

        self::assertSame(ExitCode::OK, $build->getStatusCode(), $build->getDisplay());
        self::assertSame(
            '<p>Special page.</p>',
            trim((string) file_get_contents($this->root . '/output/special/index.html')),
        );
        self::assertNotSame([], glob($this->root . '/output/assets/themes/wonder/style*.css'));
    }

    /**
     * @return list<string> Paths of every file under the output directory, sorted.
     */
    private function files(string $outputDir): array
    {
        $base = $this->root . '/' . $outputDir;
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            $files[] = substr($file->getPathname(), strlen($base) + 1);
        }
        sort($files);

        return $files;
    }

    public function testInvalidLockFileIsOnlyAWarningForBuild(): void
    {
        file_put_contents($this->root . '/yiipress.lock', '{"lock": 1, "extensions": []');

        $build = $this->build('output');

        self::assertSame(ExitCode::OK, $build->getStatusCode(), $build->getDisplay());
        self::assertStringContainsString('Warning: yiipress.lock is not valid JSON', $build->getDisplay());
        self::assertFileExists($this->root . '/output/index.html');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function getTheme(array $options): CommandTester
    {
        $archiveFile = dirname(__DIR__) . '/Support/Data/extension/wonder-v1.0.0.tar.gz';
        $archive = (string) file_get_contents($archiveFile);
        // The tag's extension.yaml, as raw.githubusercontent.com would serve it.
        $manifest = (string) file_get_contents('phar://' . $archiveFile . '/theme-wonder-1.0.0/extension.yaml');
        $http = new FakeHttpClient()->gitHub(self::REPOSITORY, [
            'v0.9.0' => ['manifest' => null],
            'v1.0.0' => ['manifest' => $manifest, 'archive' => $archive],
            'v1.1.0-beta' => ['prerelease' => true],
        ]);
        $tester = new CommandTester(new GetThemeCommand(
            $this->root,
            $http,
            EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        ));
        $tester->execute($options);

        return $tester;
    }

    private function build(string $outputDir): CommandTester
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', dirname(__DIR__, 2) . '/themes/minimal'));
        $tester = new CommandTester(new BuildCommand(
            rootPath: $this->root,
            contentPipeline: new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())),
            feedPipeline: new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())),
            themeRegistry: $registry,
            templateResolver: new TemplateResolver($registry),
        ));
        $tester->execute([
            '--content-dir' => 'content',
            '--output-dir' => $outputDir,
            '--workers' => '1',
            '--no-cache' => true,
        ]);

        return $tester;
    }
}

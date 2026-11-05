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
use YiiPress\Console\RemoveThemeCommand;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\Platform;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Render\MarkdownRenderer;
use YiiPress\Tests\Support\FakeHttpClient;
use YiiPress\Tests\Support\TarGzBuilder;
use Yiisoft\Yii\Console\ExitCode;

use function array_combine;
use function array_keys;
use function array_map;
use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function sha1;
use function sort;
use function strlen;
use function substr;
use function sys_get_temp_dir;
use function trim;
use function uniqid;

/**
 * Installed themes resolve files through their declared fallback, so installing or removing a theme that is not
 * used never changes the built site.
 */
final class ThemeFallbackTest extends TestCase
{
    /** A partial theme: only `entry.php`, which prints a UI text of the bundled translations. */
    private const array WONDER = [
        'entry.php' => "<?php echo '<main>' . \$content . '</main><nav>' . \$ui->get('archive') . '</nav>';\n",
    ];

    /** A theme that has every kind of file the active one lacks. */
    private const array RIVAL = [
        'entry.php' => "<?php echo 'RIVAL ' . \$content . ' ' . \$ui->get('archive');\n",
        'landing.php' => "<?php echo 'RIVAL LANDING';\n",
        'partials/footer.php' => "<?php echo 'RIVAL FOOTER';\n",
        'translation/en.yaml' => "archive: \"Rival archive\"\n",
        'assets/style.css' => "body{color:red}\n",
        'assets/rival.js' => "console.log('rival');\n",
    ];

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-theme-fallback-e2e-' . uniqid();
        mkdir($this->root . '/content', 0o755, true);
        file_put_contents(
            $this->root . '/content/config.yaml',
            "title: Fallback Site\nbase_url: https://example.com\nlanguages: [en]\n",
        );
        file_put_contents(
            $this->root . '/content/index.md',
            "---\ntitle: Home\npermalink: /\n---\n\nHello.\n",
        );
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testPartialThemeTakesMissingFilesFromMinimal(): void
    {
        $this->install('acme/wonder', self::WONDER);

        $this->build('output');

        self::assertSame(
            '<main><p>Hello.</p></main><nav>Archive</nav>',
            trim((string) file_get_contents($this->root . '/output/index.html')),
        );
    }

    public function testInstallingAndRemovingAnUnusedThemeKeepsTheOutput(): void
    {
        $this->install('acme/wonder', self::WONDER);
        $this->build('before');

        $this->install('acme/rival', self::RIVAL, activate: false);
        $this->build('installed');
        $remove = new CommandTester(new RemoveThemeCommand($this->root));
        $remove->execute(['id' => 'acme/rival']);
        self::assertSame(ExitCode::OK, $remove->getStatusCode(), $remove->getDisplay());
        $this->build('removed');

        self::assertSame($this->contents('before'), $this->contents('installed'));
        self::assertSame($this->contents('before'), $this->contents('removed'));
    }

    public function testThemeOfOneEntryDoesNotFillInForTheSiteTheme(): void
    {
        $this->install('acme/wonder', self::WONDER);
        $this->build('before');
        file_put_contents(
            $this->root . '/content/special.md',
            "---\ntitle: Special\npermalink: /special/\ntheme: rival\n---\n\nSpecial.\n",
        );

        $this->install('acme/rival', self::RIVAL, activate: false);
        $this->build('after');

        self::assertSame(
            file_get_contents($this->root . '/before/index.html'),
            file_get_contents($this->root . '/after/index.html'),
        );
        self::assertSame(
            'RIVAL <p>Special.</p> Rival archive',
            trim((string) file_get_contents($this->root . '/after/special/index.html')),
        );
    }

    public function testThemeWithoutFallbackStillUsesProjectTemplates(): void
    {
        $complete = self::WONDER + ['errors/404.php' => "<?php echo 'Not found';\n"];
        $this->install('acme/wonder', $complete, "theme:\n  fallback: none\n");
        mkdir($this->root . '/content/templates');
        file_put_contents($this->root . '/content/templates/landing.php', "<?php echo 'LOCAL ' . \$content;\n");
        file_put_contents(
            $this->root . '/content/landing.md',
            "---\ntitle: Landing\npermalink: /landing/\nlayout: landing\n---\n\nWelcome.\n",
        );

        $this->build('output');

        self::assertSame(
            'LOCAL <p>Welcome.</p>',
            trim((string) file_get_contents($this->root . '/output/landing/index.html')),
        );
        self::assertStringContainsString(
            '"fallback": "none"',
            (string) file_get_contents($this->root . '/yiipress.lock'),
        );
    }

    /**
     * @param array<string, string> $files Package files.
     */
    private function install(string $id, array $files, string $themeSection = '', bool $activate = true): void
    {
        [$owner, $name] = explode('/', $id);
        $manifest = "manifest: 1\nid: $id\ntype: theme\nname: " . $name . "\ndescription: A theme.\n"
            . "version: 1.0.0\nyiipress: \">=0.1.0\"\nlicense: BSD-3-Clause\nauthors: [{name: Jane Doe}]\n"
            . $themeSection;
        $prefix = "theme-$name-1.0.0";
        $archive = TarGzBuilder::gitArchive($prefix, sha1($id))
            ->file($prefix . '/extension.yaml', $manifest)
            ->directory($prefix . '/package/');
        $directories = [];
        foreach (array_keys($files) as $path) {
            $directory = dirname($path);
            if ($directory !== '.' && !isset($directories[$directory])) {
                $directories[$directory] = true;
                $archive->directory($prefix . '/package/' . $directory . '/');
            }
        }
        foreach ($files as $path => $contents) {
            $archive->file($prefix . '/package/' . $path, $contents);
        }
        $repository = "$owner/theme-$name";
        $http = new FakeHttpClient()->gitHub($repository, [
            'v1.0.0' => ['manifest' => $manifest, 'archive' => $archive->gzip()],
        ]);

        $tester = new CommandTester(new GetThemeCommand(
            $this->root,
            $http,
            EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        ));
        $tester->execute(['--url' => 'https://github.com/' . $repository, '--no-activate' => !$activate]);
        self::assertSame(ExitCode::OK, $tester->getStatusCode(), $tester->getDisplay());
    }

    private function build(string $outputDir): void
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme(Theme::BUNDLED, dirname(__DIR__, 2) . '/themes/minimal'));
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
        self::assertSame(ExitCode::OK, $tester->getStatusCode(), $tester->getDisplay());
    }

    /**
     * @return array<string, string> Path under the output directory → contents, sorted by path.
     */
    private function contents(string $outputDir): array
    {
        $base = $this->root . '/' . $outputDir;
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            $files[] = substr($file->getPathname(), strlen($base) + 1);
        }
        sort($files);

        return array_combine(
            $files,
            array_map(static fn(string $file): string => (string) file_get_contents($base . '/' . $file), $files),
        );
    }
}

<?php

declare(strict_types=1);

namespace YiiPress\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Console\BuildCommand;
use YiiPress\Console\GetProcessorCommand;
use YiiPress\Console\RemoveProcessorCommand;
use YiiPress\Console\UpdateProcessorCommand;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Platform;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Render\MarkdownRenderer;
use YiiPress\Tests\Support\FakeHttpClient;
use YiiPress\Tests\Support\ProcessorFixture;
use Yiisoft\Yii\Console\ExitCode;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function function_exists;
use function mkdir;
use function opcache_invalidate;
use function strpos;
use function substr;
use function sys_get_temp_dir;
use function uniqid;

/**
 * An installed processor stays disabled until the site lists its entry points; the printed setting enables it and
 * keeps the discovered processors running first.
 */
final class GetProcessorTest extends TestCase
{
    private const string CONFIG = "title: Processor Site\nbase_url: https://example.com\nlanguages: [en]\n";

    /** The entry point loads a support file of the package, which is never run on its own. */
    private const array PACKAGE = [
        'toc.php' => "<?php\nreturn require __DIR__ . '/lib/mark.php';\n",
        'lib/mark.php' => "<?php\nreturn static fn(string \$content): string => \$content . \"\\n\\nPACKAGE-MARK\\n\";\n",
    ];

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-processor-e2e-' . uniqid();
        mkdir($this->root . '/content/processors', 0o755, true);
        file_put_contents($this->root . '/content/config.yaml', self::CONFIG);
        file_put_contents($this->root . '/content/index.md', "---\ntitle: Home\npermalink: /\n---\n\nHello.\n");
        file_put_contents(
            $this->root . '/content/processors/10-local.php',
            "<?php\nreturn static fn(string \$content): string => \$content . \"\\n\\nLOCAL-MARK\\n\";\n",
        );
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testInstalledProcessorRunsOnlyOnceTheSiteListsItsEntryPoint(): void
    {
        $display = $this->get(ProcessorFixture::http(['v1.0.0' => ProcessorFixture::release('1.0.0', self::PACKAGE)]));

        self::assertStringContainsString(
            'Installed acme/toc into content/extensions/processors/acme/toc.',
            $display,
        );
        self::assertSame(self::CONFIG, file_get_contents($this->root . '/content/config.yaml'));
        self::assertSame(
            'content/extensions/processors/acme/toc',
            LockFile::read($this->root . '/yiipress.lock')->extensions()['acme/toc']->destination,
        );
        self::assertFileExists($this->root . '/content/extensions/processors/acme/toc/lib/mark.php');

        $html = $this->build('before');
        self::assertStringContainsString('LOCAL-MARK', $html);
        self::assertStringNotContainsString('PACKAGE-MARK', $html);

        file_put_contents($this->root . '/content/config.yaml', self::CONFIG . self::snippet($display));
        $html = $this->build('after');
        self::assertDirectoryDoesNotExist($this->root . '/after/extensions', 'Package files are never published.');
        $local = strpos($html, 'LOCAL-MARK');
        $package = strpos($html, 'PACKAGE-MARK');
        self::assertNotFalse($local, $html);
        self::assertNotFalse($package, $html);
        self::assertLessThan($package, $local, 'Discovered processors run first.');
    }

    public function testUpdateKeepsItEnabledAndRemovalWaitsUntilItIsDisabled(): void
    {
        $v2 = [
            'toc.php' => self::PACKAGE['toc.php'],
            'lib/mark.php' => "<?php\nreturn static fn(string \$content): string => \$content . \"\\n\\nPACKAGE-V2\\n\";\n",
        ];
        $http = ProcessorFixture::http([
            'v1.0.0' => ProcessorFixture::release('1.0.0', self::PACKAGE),
            'v1.1.0' => ProcessorFixture::release('1.1.0', $v2),
        ]);
        $display = $this->get($http, ['--version' => '1.0.0']);
        $enabled = self::CONFIG . self::snippet($display);
        file_put_contents($this->root . '/content/config.yaml', $enabled);

        $update = new CommandTester(new UpdateProcessorCommand(
            $this->root,
            $http,
            EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        ));
        $update->execute(['id' => 'acme/toc']);
        self::assertSame(ExitCode::OK, $update->getStatusCode(), $update->getDisplay());
        self::assertStringContainsString('Updated acme/toc from 1.0.0 to 1.1.0', $update->getDisplay());
        self::assertSame($enabled, file_get_contents($this->root . '/content/config.yaml'));
        $html = $this->build('updated');
        self::assertStringContainsString('PACKAGE-V2', $html);
        self::assertStringNotContainsString('PACKAGE-MARK', $html);

        $remove = new CommandTester(new RemoveProcessorCommand($this->root));
        $remove->execute(['id' => 'acme/toc']);
        self::assertNotSame(ExitCode::OK, $remove->getStatusCode());
        self::assertStringContainsString('is still enabled in content/config.yaml', $remove->getDisplay());
        self::assertFileExists($this->root . '/content/extensions/processors/acme/toc/toc.php');

        file_put_contents($this->root . '/content/config.yaml', self::CONFIG);
        $remove->execute(['id' => 'acme/toc']);
        self::assertSame(ExitCode::OK, $remove->getStatusCode(), $remove->getDisplay());
        self::assertFileDoesNotExist($this->root . '/content/extensions/processors/acme/toc');
        self::assertStringContainsString('LOCAL-MARK', $this->build('removed'));
    }

    public function testCachedBuildSeesChangedSupportFileOfEnabledPackage(): void
    {
        $display = $this->get(ProcessorFixture::http(['v1.0.0' => ProcessorFixture::release('1.0.0', self::PACKAGE)]));
        file_put_contents($this->root . '/content/config.yaml', self::CONFIG . self::snippet($display));
        self::assertStringContainsString('PACKAGE-MARK', $this->build('cached', cache: true));

        $support = $this->root . '/content/extensions/processors/acme/toc/lib/mark.php';
        file_put_contents(
            $support,
            "<?php\nreturn static fn(string \$content): string => \$content . \"\\n\\nEDITED-MARK\\n\";\n",
        );
        // Both builds run in this process; a real build is a process of its own, which opcache does not span.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($support, true);
        }

        self::assertStringContainsString('EDITED-MARK', $this->build('cached', cache: true));
    }

    public function testCustomContentDirectory(): void
    {
        mkdir($this->root . '/site');
        file_put_contents($this->root . '/site/config.yaml', self::CONFIG);

        $display = $this->get(
            ProcessorFixture::http(['v1.0.0' => ProcessorFixture::release('1.0.0', self::PACKAGE)]),
            ['--content-dir' => 'site'],
        );

        self::assertStringContainsString('Installed acme/toc into site/extensions/processors/acme/toc.', $display);
        self::assertStringContainsString('To run it, set "processors" in site/config.yaml to:', $display);
        self::assertStringContainsString('      - extensions/processors/acme/toc/toc.php', $display);
        self::assertFileExists($this->root . '/site/extensions/processors/acme/toc/toc.php');
    }

    public function testRefusesTypeOtherThanProcessor(): void
    {
        $theme = "manifest: 1\nid: acme/toc\ntype: theme\nname: TOC\ndescription: A theme.\nversion: 1.0.0\n"
            . "yiipress: \">=0.1.0\"\nlicense: MIT\nauthors: [{name: Jane Doe}]\n";
        $http = new FakeHttpClient()->gitHub('acme/processor-toc', ['v1.0.0' => ['manifest' => $theme]]);

        $tester = $this->tester($http);
        $tester->execute(['--url' => 'https://github.com/acme/processor-toc']);

        self::assertNotSame(ExitCode::OK, $tester->getStatusCode());
        self::assertFileDoesNotExist($this->root . '/yiipress.lock');
        self::assertFileDoesNotExist($this->root . '/content/extensions');
    }

    /**
     * @param array<string, string> $options
     */
    private function get(FakeHttpClient $http, array $options = []): string
    {
        $tester = $this->tester($http);
        $tester->execute(['--url' => 'https://github.com/acme/processor-toc', ...$options]);
        self::assertSame(ExitCode::OK, $tester->getStatusCode(), $tester->getDisplay());

        return $tester->getDisplay();
    }

    private function tester(FakeHttpClient $http): CommandTester
    {
        return new CommandTester(new GetProcessorCommand(
            $this->root,
            $http,
            EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        ));
    }

    /**
     * The printed `processors` setting, from its first line to the blank line after it.
     */
    private static function snippet(string $display): string
    {
        $start = strpos($display, "processors:\n");
        self::assertNotFalse($start, $display);
        $end = strpos($display, "\n\n", $start);
        self::assertNotFalse($end, $display);

        return substr($display, $start, $end - $start + 1);
    }

    private function build(string $outputDir, bool $cache = false): string
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
            '--no-cache' => !$cache,
        ]);
        self::assertSame(ExitCode::OK, $tester->getStatusCode(), $tester->getDisplay());

        return (string) file_get_contents($this->root . '/' . $outputDir . '/index.html');
    }
}

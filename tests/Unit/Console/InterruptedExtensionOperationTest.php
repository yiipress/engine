<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Console\BuildCommand;
use YiiPress\Console\RecoverExtensionsCommand;
use YiiPress\Console\ServeCommand;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Render\MarkdownRenderer;
use Yiisoft\Yii\Console\ExitCode;

use function chdir;
use function dirname;
use function file_exists;
use function file_put_contents;
use function getcwd;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Builds and `serve` stop while `.yiipress/journal.json` exists; `recover:extensions` clears it.
 */
final class InterruptedExtensionOperationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-interrupted-test-' . uniqid();
        mkdir($this->root . '/content', 0o755, true);
        mkdir($this->root . '/.yiipress');
        file_put_contents($this->root . '/content/config.yaml', "title: Site\nlanguages: [en]\n");
        file_put_contents(
            $this->root . '/.yiipress/journal.json',
            '{"journal": 1, "tx": "0123456789abcdef", "operation": "install", "state": "committed", "steps": []}',
        );
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testBuildRefusesToRun(): void
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));
        $tester = new CommandTester(new BuildCommand(
            rootPath: $this->root,
            contentPipeline: new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())),
            feedPipeline: new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())),
            themeRegistry: $registry,
            templateResolver: new TemplateResolver($registry),
        ));

        $exitCode = $tester->execute(['--content-dir' => 'content', '--output-dir' => 'output']);

        self::assertSame(ExitCode::TEMPFAIL, $exitCode);
        self::assertStringContainsString('Run `yiipress recover:extensions`.', $tester->getDisplay());
        self::assertFalse(file_exists($this->root . '/output'));
    }

    public function testServeRefusesToRun(): void
    {
        $previous = (string) getcwd();
        chdir($this->root);
        try {
            $tester = new CommandTester(new ServeCommand());
            $exitCode = $tester->execute(['--content-dir' => 'content', '--output-dir' => 'output']);
        } finally {
            chdir($previous);
        }

        self::assertSame(ExitCode::TEMPFAIL, $exitCode);
        self::assertStringContainsString('An extension operation was interrupted.', $tester->getDisplay());
    }

    public function testRecoverCommandClearsJournal(): void
    {
        $tester = new CommandTester(new RecoverExtensionsCommand($this->root));

        $exitCode = $tester->execute([]);

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertStringContainsString('had completed', $tester->getDisplay());
        self::assertFalse(file_exists($this->root . '/.yiipress/journal.json'));
    }

    public function testRecoverCommandWithNothingPending(): void
    {
        $tester = new CommandTester(new RecoverExtensionsCommand($this->root));
        $tester->execute([]);

        $tester->execute([]);

        self::assertSame("No interrupted extension operation found.\n", $tester->getDisplay());
    }
}

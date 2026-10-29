<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Processor;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\Manifest\ManifestParser;
use YiiPress\Extension\Manifest\ProcessorMetadata;
use YiiPress\Extension\Processor\EnablementSnippet;
use YiiPress\Tests\Support\ProcessorFixture;

use function file_put_contents;
use function implode;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class EnablementSnippetTest extends TestCase
{
    private const string PACKAGE = 'extensions/processors/acme/toc';
    private const string CONFIG = "title: Blog\nlanguages: [en]\n";

    private string $content;

    protected function setUp(): void
    {
        $this->content = sys_get_temp_dir() . '/yiipress-enablement-test-' . uniqid();
        mkdir($this->content . '/processors', 0o755, true);
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->content);
    }

    public function testKeepsDiscoveredProcessorsFirstInFilenameOrder(): void
    {
        file_put_contents($this->content . '/config.yaml', self::CONFIG);
        file_put_contents($this->content . '/processors/20-late.php', '<?php');
        file_put_contents($this->content . '/processors/10-early.php', '<?php');

        self::assertSame(<<<'TEXT'
            Not enabled: installing changes no configuration. Entry points: extensions/processors/acme/toc/toc.php (before_markdown).
            To run it, set "processors" in content/config.yaml to:

            processors:
              content:
                before_markdown:
                  - processors/10-early.php
                  - processors/20-late.php
                  - extensions/processors/acme/toc/toc.php
              feed:
                before_markdown:
                  - processors/10-early.php
                  - processors/20-late.php
                  - extensions/processors/acme/toc/toc.php

            List order is execution order. Remove the feed lists to leave feeds as they are.
            Listing processors turns off discovery of processors/*.php, so the discovered ones are listed first, in filename order, to keep them running.
            TEXT, $this->render());
    }

    public function testWithoutConfigOrDiscoveredFilesListsOnlyEntryPoints(): void
    {
        $text = $this->render();

        self::assertStringContainsString(
            "processors:\n  content:\n    before_markdown:\n      - extensions/processors/acme/toc/toc.php\n"
            . "  feed:\n    before_markdown:\n      - extensions/processors/acme/toc/toc.php\n",
            $text,
        );
        self::assertStringNotContainsString('discovery', $text);
    }

    public function testAddsToExplicitSettingUnderFirstDeclaredStage(): void
    {
        file_put_contents($this->content . '/config.yaml', self::CONFIG . <<<'YAML'
            processors:
              content:
                before_markdown: [processors/a.php]
                after_markdown: [processors/b.php]
              feed:
                after_markdown: [extensions/processors/acme/toc/late.php]
            YAML);
        file_put_contents($this->content . '/processors/ignored.php', '<?php');

        $text = $this->render(
            "    - path: late.php\n      stages: [after_markdown, before_markdown]\n"
            . "    - path: early.php\n      stages: [before_markdown]\n",
        );

        self::assertStringContainsString(<<<'YAML'
            processors:
              content:
                before_markdown:
                  - processors/a.php
                  - extensions/processors/acme/toc/early.php
                after_markdown:
                  - processors/b.php
                  - extensions/processors/acme/toc/late.php
              feed:
                before_markdown:
                  - extensions/processors/acme/toc/early.php
                after_markdown:
                  - extensions/processors/acme/toc/late.php

            YAML, $text);
        self::assertStringContainsString('late.php (after_markdown, before_markdown); ', $text);
        self::assertStringNotContainsString('ignored.php', $text);
    }

    public function testListFormRunsBeforeMarkdownForPagesAndFeeds(): void
    {
        file_put_contents($this->content . '/config.yaml', self::CONFIG . "processors: [processors/a.php]\n");

        self::assertStringContainsString(
            "  content:\n    before_markdown:\n      - processors/a.php\n      - " . self::PACKAGE . "/toc.php\n"
            . "  feed:\n    before_markdown:\n      - processors/a.php\n      - " . self::PACKAGE . "/toc.php\n",
            $this->render(),
        );
    }

    public function testQuotesPathsThatAreNotPlain(): void
    {
        file_put_contents($this->content . '/config.yaml', self::CONFIG . "processors: [\"it's here.php\"]\n");

        self::assertStringContainsString("      - 'it''s here.php'\n", $this->render());
    }

    public function testUnreadableConfigShowsEntryPointsAndSaysWhy(): void
    {
        file_put_contents($this->content . '/config.yaml', "title: Blog\nlanguages: 5\n");
        file_put_contents($this->content . '/processors/10-early.php', '<?php');

        $text = $this->render();

        self::assertStringContainsString(
            "  content:\n    before_markdown:\n      - " . self::PACKAGE . "/toc.php\n  feed:",
            $text,
        );
        self::assertStringContainsString(
            'The current "processors" setting is not shown, since content/config.yaml could not be read: ',
            $text,
        );
    }

    private function render(?string $entryPoints = null): string
    {
        $manifest = new ManifestParser()->parse($entryPoints === null
            ? ProcessorFixture::manifest()
            : ProcessorFixture::manifest(entryPoints: $entryPoints));
        $processor = $manifest->processor();
        self::assertInstanceOf(ProcessorMetadata::class, $processor);

        return implode("\n", new EnablementSnippet()->lines(
            $this->content,
            'content/config.yaml',
            self::PACKAGE,
            $processor,
        ));
    }
}

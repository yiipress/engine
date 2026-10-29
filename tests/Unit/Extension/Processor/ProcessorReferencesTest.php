<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Processor;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Processor\ProcessorReference;
use YiiPress\Extension\Processor\ProcessorReferences;

use function array_map;
use function file_put_contents;
use function mkdir;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;

final class ProcessorReferencesTest extends TestCase
{
    private const string CONFIG = "title: Blog\nlanguages: [en]\n";

    private string $content;
    private string $package;

    protected function setUp(): void
    {
        $this->content = sys_get_temp_dir() . '/yiipress-processor-references-test-' . uniqid();
        $this->package = $this->content . '/extensions/processors/acme/toc';
        mkdir($this->package, 0o755, true);
        mkdir($this->content . '/extensions/processors/acme/toc-extra', 0o755, true);
        mkdir($this->content . '/processors');
        file_put_contents($this->package . '/toc.php', '<?php');
        file_put_contents($this->content . '/extensions/processors/acme/toc-extra/x.php', '<?php');
        file_put_contents($this->content . '/processors/local.php', '<?php');
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->content);
    }

    public function testFindsEveryConfiguredPathIntoThePackage(): void
    {
        file_put_contents($this->content . '/config.yaml', self::CONFIG . <<<YAML
            processors:
              content:
                before_markdown: [processors/local.php, extensions/processors/acme/toc/toc.php]
                after_markdown: [./extensions/processors/acme/../acme/toc/missing.php]
              feed:
                after_markdown: [{$this->package}/toc.php, extensions/processors/acme/toc-extra/x.php]
            YAML);

        self::assertSame([
            'content.before_markdown: extensions/processors/acme/toc/toc.php',
            'content.after_markdown: ./extensions/processors/acme/../acme/toc/missing.php',
            'feed.after_markdown: ' . $this->package . '/toc.php',
        ], $this->find());
    }

    public function testListFormCountsForPagesAndFeeds(): void
    {
        file_put_contents($this->content . '/config.yaml', self::CONFIG . "processors: [extensions/processors/acme/toc/toc.php]\n");

        self::assertSame([
            'content.before_markdown: extensions/processors/acme/toc/toc.php',
            'feed.before_markdown: extensions/processors/acme/toc/toc.php',
        ], $this->find());
    }

    public function testFindsLinksIntoThePackage(): void
    {
        symlink($this->package . '/toc.php', $this->content . '/processors/linked.php');
        symlink($this->package, $this->content . '/toc');
        file_put_contents($this->content . '/config.yaml', self::CONFIG);

        self::assertSame(['processors/linked.php (discovered)'], $this->find());

        file_put_contents($this->content . '/config.yaml', self::CONFIG . "processors: [toc/toc.php]\n");

        self::assertSame([
            'content.before_markdown: toc/toc.php',
            'feed.before_markdown: toc/toc.php',
        ], $this->find());
    }

    public function testGivesStageAndFileInPackage(): void
    {
        symlink($this->package, $this->content . '/toc');
        file_put_contents($this->content . '/config.yaml', self::CONFIG . <<<'YAML'
            processors:
              feed:
                after_markdown: [extensions/processors/acme/./toc/sub/../toc.php, toc/toc.php]
            YAML);

        $references = new ProcessorReferences()->find($this->content, $this->package);

        self::assertCount(2, $references);
        foreach ($references as $reference) {
            self::assertSame('feed', $reference->pipeline);
            self::assertSame('after_markdown', $reference->stage);
            self::assertSame('toc.php', $reference->packagePath);
        }
    }

    public function testNothingWithoutConfigOrWithDiscoveryOnly(): void
    {
        self::assertSame([], $this->find());

        file_put_contents($this->content . '/config.yaml', self::CONFIG);

        self::assertSame([], $this->find());
    }

    public function testUnreadableConfigIsAnError(): void
    {
        file_put_contents($this->content . '/config.yaml', "title: Blog\nlanguages: 5\n");

        try {
            $this->find();
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('content_unreadable', $exception->errorCode);
        }
    }

    /**
     * @return list<string>
     */
    private function find(): array
    {
        return array_map(
            static fn(ProcessorReference $reference): string => (string) $reference,
            new ProcessorReferences()->find($this->content, $this->package),
        );
    }
}

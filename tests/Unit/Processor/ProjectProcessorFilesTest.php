<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Content\Model\ProcessorConfig;
use YiiPress\Processor\ProjectProcessorFiles;

use function dirname;
use function file_put_contents;
use function mkdir;
use function realpath;
use function sys_get_temp_dir;
use function uniqid;

final class ProjectProcessorFilesTest extends TestCase
{
    private string $content;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/yiipress-processor-files-test-' . uniqid();
        mkdir($base . '/content/processors', 0o755, true);
        $this->content = (string) realpath($base . '/content');
        $package = $this->content . '/extensions/processors/acme/toc';
        mkdir($package . '/lib', 0o755, true);
        mkdir($this->content . '/extensions/processors/acme/other', 0o755, true);
        file_put_contents($this->content . '/processors/b.php', '<?php');
        file_put_contents($this->content . '/processors/a.php', '<?php');
        file_put_contents($package . '/toc.php', '<?php');
        file_put_contents($package . '/lib/mark.php', '<?php');
        file_put_contents($package . '/lib/words.txt', 'data');
        file_put_contents($this->content . '/extensions/processors/acme/other/x.php', '<?php');
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove(dirname($this->content));
    }

    public function testDiscoveryTracksDiscoveredFiles(): void
    {
        self::assertSame([
            $this->content . '/processors/a.php',
            $this->content . '/processors/b.php',
        ], ProjectProcessorFiles::collect($this->content, new ProcessorConfig()));
    }

    public function testConfiguredPackageEntryPointTracksEveryFileOfItsPackage(): void
    {
        $config = new ProcessorConfig(
            discover: false,
            contentAfterMarkdown: ['extensions/processors/acme/toc/toc.php'],
            feedBeforeMarkdown: ['processors/a.php'],
        );

        self::assertSame([
            $this->content . '/extensions/processors/acme/toc/lib/mark.php',
            $this->content . '/extensions/processors/acme/toc/lib/words.txt',
            $this->content . '/extensions/processors/acme/toc/toc.php',
            $this->content . '/processors/a.php',
        ], ProjectProcessorFiles::collect($this->content, $config));
    }

    public function testFileOutsidePackagesTracksItselfOnly(): void
    {
        file_put_contents($this->content . '/extensions/processors/loose.php', '<?php');
        $config = new ProcessorConfig(discover: false, contentBeforeMarkdown: [
            'extensions/processors/loose.php',
            'missing.php',
        ]);

        self::assertSame(
            [$this->content . '/extensions/processors/loose.php'],
            ProjectProcessorFiles::collect($this->content, $config),
        );
    }
}

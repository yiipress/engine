<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use App\Content\Model\Entry;
use App\Processor\AssetAwareProcessorInterface;
use App\Processor\ContentProcessorInterface;
use App\Processor\ContentProcessorPipeline;
use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class ContentProcessorPipelineTest extends TestCase
{
    public function testEmptyPipelineReturnsInputUnchanged(): void
    {
        $pipeline = new ContentProcessorPipeline();

        assertSame('hello', $pipeline->process('hello', $this->createEntry()));
    }

    public function testProcessChainsProcessorsInOrder(): void
    {
        $pipeline = new ContentProcessorPipeline(
            $this->createProcessor(fn (string $c) => $c . ' [A]'),
            $this->createProcessor(fn (string $c) => $c . ' [B]'),
        );

        $result = $pipeline->process('start', $this->createEntry());

        assertSame('start [A] [B]', $result);
    }

    public function testProcessorReceivesOutputOfPreviousProcessor(): void
    {
        $pipeline = new ContentProcessorPipeline(
            $this->createProcessor(fn (string $c) => str_replace('foo', 'bar', $c)),
            $this->createProcessor(fn (string $c) => str_replace('bar', 'baz', $c)),
        );

        $result = $pipeline->process('<p>foo</p>', $this->createEntry());

        assertSame('<p>baz</p>', $result);
    }

    public function testCollectHeadAssetsFromAssetAwareProcessors(): void
    {
        $assetProcessor = new class implements ContentProcessorInterface, AssetAwareProcessorInterface {
            public function process(string $content, Entry $entry): string { return $content; }
            public function headAssets(string $processedContent): string { return '<script src="test.js"></script>'; }
            public function assetFiles(): array { return []; }
        };

        $plainProcessor = $this->createProcessor(fn (string $c) => $c);

        $pipeline = new ContentProcessorPipeline($plainProcessor, $assetProcessor);

        assertSame('<script src="test.js"></script>', $pipeline->collectHeadAssets('content'));
    }

    public function testCollectHeadAssetsReturnsEmptyForNoAssetProcessors(): void
    {
        $pipeline = new ContentProcessorPipeline(
            $this->createProcessor(fn (string $c) => $c),
        );

        assertSame('', $pipeline->collectHeadAssets('content'));
    }

    public function testCollectAssetFilesFromAssetAwareProcessors(): void
    {
        $assetProcessor = new class implements ContentProcessorInterface, AssetAwareProcessorInterface {
            public function process(string $content, Entry $entry): string { return $content; }
            public function headAssets(string $processedContent): string { return ''; }
            public function assetFiles(): array { return ['/src/style.css' => 'assets/style.css']; }
        };

        $pipeline = new ContentProcessorPipeline($assetProcessor);

        assertSame(['/src/style.css' => 'assets/style.css'], $pipeline->collectAssetFiles());
    }

    public function testCollectAssetFilesReturnsEmptyForNoAssetProcessors(): void
    {
        $pipeline = new ContentProcessorPipeline();

        assertSame([], $pipeline->collectAssetFiles());
    }

    private function createEntry(): Entry
    {
        $tmp = tempnam(sys_get_temp_dir(), 'yiipress_pipeline_test_');
        file_put_contents($tmp, "---\ntitle: Test\n---\nBody.");
        $this->tempFiles[] = $tmp;

        return new Entry(
            filePath: $tmp,
            collection: 'blog',
            slug: 'test',
            title: 'Test',
            date: new DateTimeImmutable('2024-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '',
            layout: '',
            theme: '',
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: 0,
        );
    }

    private function createProcessor(Closure $fn): ContentProcessorInterface
    {
        return new readonly class ($fn) implements ContentProcessorInterface {
            public function __construct(private Closure $fn) {}

            public function process(string $content, Entry $entry): string
            {
                return ($this->fn)($content);
            }
        };
    }

    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

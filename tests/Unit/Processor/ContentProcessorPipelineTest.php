<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Processor\AssetProcessorInterface;
use YiiPress\Processor\ContentProcessorInterface;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\SiteConfigAwareProcessorInterface;
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
            $this->createProcessor(fn(string $c) => $c . ' [A]'),
            $this->createProcessor(fn(string $c) => $c . ' [B]'),
        );

        $result = $pipeline->process('start', $this->createEntry());

        assertSame('start [A] [B]', $result);
    }

    public function testProcessorReceivesOutputOfPreviousProcessor(): void
    {
        $pipeline = new ContentProcessorPipeline(
            $this->createProcessor(fn(string $c) => str_replace('foo', 'bar', $c)),
            $this->createProcessor(fn(string $c) => str_replace('bar', 'baz', $c)),
        );

        $result = $pipeline->process('<p>foo</p>', $this->createEntry());

        assertSame('<p>baz</p>', $result);
    }

    public function testCanInsertProcessorsBeforeMarkerProcessor(): void
    {
        $marker = new class implements ContentProcessorInterface {
            public function process(string $content, Entry $entry): string
            {
                return $content . ' [marker]';
            }
        };

        $pipeline = new ContentProcessorPipeline($marker);
        $pipeline->insertBefore($marker::class, $this->createProcessor(fn(string $c) => $c . ' [before]'));

        assertSame('start [before] [marker]', $pipeline->process('start', $this->createEntry()));
    }

    public function testCanInsertProcessorsAfterMarkerProcessor(): void
    {
        $marker = new class implements ContentProcessorInterface {
            public function process(string $content, Entry $entry): string
            {
                return $content . ' [marker]';
            }
        };

        $pipeline = new ContentProcessorPipeline($marker);
        $pipeline->insertAfter($marker::class, $this->createProcessor(fn(string $c) => $c . ' [after]'));

        assertSame('start [marker] [after]', $pipeline->process('start', $this->createEntry()));
    }

    public function testCollectHeadAssetsFromAssetAwareProcessors(): void
    {
        $assetProcessor = new class implements ContentProcessorInterface, AssetProcessorInterface {
            public function process(string $content, Entry $entry): string
            {
                return $content;
            }
            public function headAssets(string $processedContent): string
            {
                return '<script src="test.js"></script>';
            }
            public function assetFiles(): array
            {
                return [];
            }
        };

        $plainProcessor = $this->createProcessor(fn(string $c) => $c);

        $pipeline = new ContentProcessorPipeline($plainProcessor, $assetProcessor);

        assertSame('<script src="test.js"></script>', $pipeline->collectHeadAssets('content'));
    }

    public function testCollectHeadAssetsReturnsEmptyForNoAssetProcessors(): void
    {
        $pipeline = new ContentProcessorPipeline(
            $this->createProcessor(fn(string $c) => $c),
        );

        assertSame('', $pipeline->collectHeadAssets('content'));
    }

    public function testCollectAssetFilesFromAssetAwareProcessors(): void
    {
        $assetProcessor = new class implements ContentProcessorInterface, AssetProcessorInterface {
            public function process(string $content, Entry $entry): string
            {
                return $content;
            }
            public function headAssets(string $processedContent): string
            {
                return '';
            }
            public function assetFiles(): array
            {
                return ['/src/style.css' => 'assets/style.css'];
            }
        };

        $pipeline = new ContentProcessorPipeline($assetProcessor);

        assertSame(['/src/style.css' => 'assets/style.css'], $pipeline->collectAssetFiles());
    }

    public function testCollectAssetFilesReturnsEmptyForNoAssetProcessors(): void
    {
        $pipeline = new ContentProcessorPipeline();

        assertSame([], $pipeline->collectAssetFiles());
    }

    public function testApplySiteConfigPassesConfigurationToAwareProcessors(): void
    {
        $state = new class () {
            public ?string $receivedTheme = null;
        };

        $awareProcessor = new class ($state) implements ContentProcessorInterface, SiteConfigAwareProcessorInterface {
            public function __construct(private object $state)
            {
            }

            public function applySiteConfig(SiteConfig $siteConfig): void
            {
                $this->state->receivedTheme = $siteConfig->highlightTheme;
            }

            public function process(string $content, Entry $entry): string
            {
                return $content;
            }
        };

        $pipeline = new ContentProcessorPipeline($awareProcessor);
        $pipeline->applySiteConfig(new SiteConfig(
            title: 'Test',
            description: '',
            baseUrl: '',
            defaultLanguage: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
            highlightTheme: 'Solarized (dark)',
        ));

        assertSame('Solarized (dark)', $state->receivedTheme);
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

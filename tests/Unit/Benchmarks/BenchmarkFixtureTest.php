<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Benchmarks;

use YiiPress\Benchmark\BenchmarkDataGenerator;
use YiiPress\Content\Parser\EntryParser;
use YiiPress\Content\Parser\FilenameParser;
use YiiPress\Content\Parser\FrontMatterParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function dirname;
use function is_dir;
use function sys_get_temp_dir;

final class BenchmarkFixtureTest extends TestCase
{
    private EntryParser $parser;
    private string $benchmarksDir;
    private static bool $fixturesGenerated = false;

    protected function setUp(): void
    {
        $this->parser = new EntryParser(new FrontMatterParser(), new FilenameParser());

        $repositoryBenchmarksDir = dirname(__DIR__, 3) . '/benchmarks/data';
        if (is_dir($repositoryBenchmarksDir . '/content') && is_dir($repositoryBenchmarksDir . '/realistic-content')) {
            $this->benchmarksDir = $repositoryBenchmarksDir;
            return;
        }

        $this->benchmarksDir = sys_get_temp_dir() . '/yiipress-test-benchmark-data';

        if (!self::$fixturesGenerated) {
            BenchmarkDataGenerator::generateSmallDataset($this->benchmarksDir . '/content', 10);
            BenchmarkDataGenerator::generateRealisticDataset($this->benchmarksDir . '/realistic-content', 88);
            self::$fixturesGenerated = true;
        }
    }

    public function testSmallBenchmarkFixtureEntryHasTitle(): void
    {
        $entry = $this->parser->parse($this->benchmarksDir . '/content/collection-0/2024-01-01-entry-0.md', 'collection-0');

        assertSame('Entry 0: Benchmark Post', $entry->title);
    }

    public function testBenchmarkDataGeneratorIsDevAutoloaded(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($composer);
        self::assertSame('benchmarks/src', $composer['autoload-dev']['psr-4']['YiiPress\\Benchmark\\'] ?? null);
        self::assertArrayNotHasKey('YiiPress\\Benchmark\\', $composer['autoload']['psr-4'] ?? []);
    }

    public function testRealisticBenchmarkFixtureEntryHasTitle(): void
    {
        $entry = $this->parser->parse($this->benchmarksDir . '/realistic-content/collection-0/2024-01-01-entry-0.md', 'collection-0');

        assertSame('Entry 0: Comprehensive Benchmark Post', $entry->title);
    }

    public function testRealisticBenchmarkFixtureInternalLinksMatchResolvedCollections(): void
    {
        $body = (string) file_get_contents(
            $this->benchmarksDir . '/realistic-content/collection-0/2024-01-01-entry-0.md',
        );

        assertStringContainsString('/collection-0/entry-15/', $body);
        assertStringContainsString('/collection-0/entry-22/', $body);
        assertStringContainsString('/collection-0/entry-29/', $body);
    }

    public function testSmallBenchmarkFixtureCodeSamplesVaryAcrossEntries(): void
    {
        $firstBody = (string) file_get_contents(
            $this->benchmarksDir . '/content/collection-0/2024-01-01-entry-0.md',
        );
        $secondBody = (string) file_get_contents(
            $this->benchmarksDir . '/content/collection-0/2024-01-04-entry-3.md',
        );

        assertStringContainsString("ArticleRenderer('entry-0')", $firstBody);
        assertStringContainsString('This entry focuses on prose content without a source listing.', $secondBody);
    }

    public function testRealisticBenchmarkFixtureCodeSamplesVaryAcrossEntries(): void
    {
        $firstBody = (string) file_get_contents(
            $this->benchmarksDir . '/realistic-content/collection-0/2024-01-01-entry-0.md',
        );
        $secondBody = (string) file_get_contents(
            $this->benchmarksDir . '/realistic-content/collection-0/2024-01-04-entry-3.md',
        );

        assertStringContainsString('final class Chapter1Handler', $firstBody);
        assertStringContainsString('focuses on architecture rather than verbatim code.', $secondBody);
    }
}

<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Benchmarks;

use PHPUnit\Framework\TestCase;

use function array_key_exists;
use function dirname;
use function iterator_to_array;

require_once dirname(__DIR__, 3) . '/benchmarks/MarkdownParserComparisonBench.php';

final class MarkdownParserComparisonBenchTest extends TestCase
{
    public function testProviderIncludesCurrentMarkdownRenderer(): void
    {
        $bench = new \YiiPress\Benchmarks\MarkdownParserComparisonBench();
        $parsers = iterator_to_array($bench->provideParsers());

        self::assertTrue(array_key_exists('mdparser', $parsers));
        self::assertSame(['parser' => 'mdparser'], $parsers['mdparser']);
    }

    public function testCurrentRendererBenchmarkSubjectRuns(): void
    {
        $bench = new \YiiPress\Benchmarks\MarkdownParserComparisonBench();
        $params = ['parser' => 'mdparser'];

        $bench->setUp($params);
        $bench->benchRenderShortMarkdown($params);
        $bench->benchRenderMediumMarkdown($params);
        $bench->benchRenderLargeMarkdown($params);

        self::expectNotToPerformAssertions();
    }
}

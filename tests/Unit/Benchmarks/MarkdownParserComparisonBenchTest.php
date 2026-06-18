<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Benchmarks;

use PHPUnit\Framework\TestCase;

use function array_key_exists;
use function class_exists;
use function dirname;
use function iterator_to_array;

require_once dirname(__DIR__, 3) . '/benchmarks/MarkdownParserComparisonBench.php';

final class MarkdownParserComparisonBenchTest extends TestCase
{
    public function testProviderAlwaysIncludesCurrentMarkdownRenderer(): void
    {
        $bench = new \YiiPress\Benchmarks\MarkdownParserComparisonBench();
        $parsers = iterator_to_array($bench->provideParsers());

        self::assertTrue(array_key_exists('yiipress-markdown', $parsers));
        self::assertSame(['parser' => 'yiipress-markdown'], $parsers['yiipress-markdown']);
    }

    public function testProviderIncludesMdParserOnlyWhenExtensionIsAvailable(): void
    {
        $bench = new \YiiPress\Benchmarks\MarkdownParserComparisonBench();
        $parsers = iterator_to_array($bench->provideParsers());

        self::assertSame(class_exists('MdParser\\Parser'), array_key_exists('mdparser', $parsers));
    }

    public function testCurrentRendererBenchmarkSubjectRunsWithoutMdParser(): void
    {
        $bench = new \YiiPress\Benchmarks\MarkdownParserComparisonBench();
        $params = ['parser' => 'yiipress-markdown'];

        $bench->setUp($params);
        $bench->benchRenderShortMarkdown($params);
        $bench->benchRenderMediumMarkdown($params);
        $bench->benchRenderLargeMarkdown($params);

        self::expectNotToPerformAssertions();
    }
}

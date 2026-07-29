<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\LatexMath\LatexMathProcessor;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Processor\Shortcode\CodeGroupProcessor;
use YiiPress\Processor\SyntaxHighlightProcessor;
use Yiisoft\Definitions\Reference;

use function dirname;

final class ContentProcessorPipelineConfigTest extends TestCase
{
    public function testContentPipelineIncludesAssetsAndTwoPassCodeGroupProcessors(): void
    {
        $definitions = require dirname(__DIR__, 3) . '/config/common/di/content-pipeline.php';
        $referenceId = new ReflectionProperty(Reference::class, 'id');
        $registeredProcessorIds = [];

        foreach ($definitions['contentPipeline']['__construct()'] as $reference) {
            self::assertInstanceOf(Reference::class, $reference);
            $registeredProcessorIds[] = $referenceId->getValue($reference);
        }

        self::assertContains(LatexMathProcessor::class, $registeredProcessorIds);
        $codeGroupPositions = array_keys(
            array_filter(
                $registeredProcessorIds,
                static fn(string $processorId): bool => $processorId === CodeGroupProcessor::class,
            ),
        );
        $markdownPosition = array_search(MarkdownProcessor::class, $registeredProcessorIds, true);
        $syntaxHighlightPosition = array_search(SyntaxHighlightProcessor::class, $registeredProcessorIds, true);
        self::assertNotFalse($markdownPosition);
        self::assertNotFalse($syntaxHighlightPosition);
        self::assertSame(
            [
                $markdownPosition - 1,
                $syntaxHighlightPosition + 1,
            ],
            $codeGroupPositions,
        );

        $pipeline = new ContentProcessorPipeline(new LatexMathProcessor());
        self::assertStringContainsString(
            'assets/plugins/latex-math.js',
            $pipeline->collectHeadAssets('<p><span class="math">x</span></p>'),
        );
    }
}

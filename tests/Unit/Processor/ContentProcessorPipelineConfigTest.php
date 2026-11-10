<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\LatexMath\LatexMathProcessor;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Processor\OEmbed\OEmbedProcessor;
use YiiPress\Processor\Question\QuestionProcessor;
use YiiPress\Processor\RootRelativeLinkProcessor;
use YiiPress\Processor\Shortcode\CodeGroupProcessor;
use YiiPress\Processor\SyntaxHighlightProcessor;
use YiiPress\Render\MarkdownRenderer;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

use function dirname;

final class ContentProcessorPipelineConfigTest extends TestCase
{
    public function testConfiguredPagePipelineKeepsMalformedCodeGroupLiteral(): void
    {
        $pipeline = $this->container()->get('contentPipeline');
        $markdown = "Before.\n[code-group]\n[code-tab label=\"\"]\nOne.\n[/code-tab]\n"
            . "[code-tab label=\"Two\"]\nTwo.\n[/code-tab]\n[/code-group]\nAfter.";

        $result = $pipeline->process($markdown, $this->entry());

        self::assertSame(new MarkdownRenderer()->render($markdown), $result);
    }

    public function testConfiguredPageAndFeedPipelinesReuseFixedQuestionPasses(): void
    {
        $container = $this->container();
        $page = $container->get('contentPipeline');
        $feed = $container->get('feedPipeline');
        $markdown = "<!-- yiipress-questions-preserved -->\n\n"
            . "[question title=\"Real question?\"]\nAnswer.\n[/question]";
        $first = $this->entry();
        $next = $this->entry();
        $next->faqLevel = 0;

        $results = [
            $page->process($markdown, $first),
            $feed->process($markdown, $first),
            $page->process($markdown, $next),
            $feed->process($markdown, $next),
            $page->process($markdown, $first),
        ];

        foreach ($results as $result) {
            self::assertStringContainsString('<summary>Real question?</summary>', $result);
            self::assertStringContainsString('<!-- yiipress-questions-preserved -->', $result);
            self::assertStringNotContainsString('[question', $result);
        }
        self::assertSame($results[0], $results[1]);
        self::assertSame($results[0], $results[4]);
        self::assertSame($results[2], $results[3]);
        self::assertStringContainsString('class="faq-section"', $results[2]);
    }

    public function testConfiguredCodeGroupHasOneAssetPairAndResetsRootPath(): void
    {
        $pipeline = $this->container()->get('contentPipeline');
        $markdown = "[code-group]\n[code-tab label=\"One\"]\n```text\none\n```\n[/code-tab]\n"
            . "[code-tab label=\"Two\"]\n```text\ntwo\n```\n[/code-tab]\n[/code-group]";

        $result = $pipeline->process($markdown, $this->entry(), '../../');
        $nestedAssets = $pipeline->collectHeadAssets($result);
        $repeated = $pipeline->process($markdown, $this->entry());
        $rootAssets = $pipeline->collectHeadAssets($repeated);

        self::assertStringContainsString('class="code-group"', $result);
        self::assertStringContainsString('data-language="text"', $result);
        self::assertSame(1, substr_count($nestedAssets, 'code-groups.css'));
        self::assertSame(1, substr_count($nestedAssets, 'code-groups.js'));
        self::assertStringContainsString('href="../../assets/plugins/code-groups.css"', $nestedAssets);
        self::assertStringContainsString('src="../../assets/plugins/code-groups.js"', $nestedAssets);
        self::assertStringContainsString('href="./assets/plugins/code-groups.css"', $rootAssets);
        self::assertStringContainsString('src="./assets/plugins/code-groups.js"', $rootAssets);
        self::assertSame($result, $repeated);
    }

    private function container(): Container
    {
        $definitions = require dirname(__DIR__, 3) . '/config/common/di/content-pipeline.php';

        return new Container(ContainerConfig::create()->withDefinitions($definitions));
    }

    private function entry(): Entry
    {
        return new Entry(
            filePath: __FILE__,
            collection: 'docs',
            slug: 'test',
            title: 'Test',
            date: null,
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '/docs/test/',
            layout: '',
            theme: '',
            weight: 0,
            language: 'en',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: 0,
        );
    }

    public function testContentPipelineIncludesAssetsAndTwoPassProcessors(): void
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
                static fn(string $processorId): bool => in_array(
                    $processorId,
                    [CodeGroupProcessor::class, 'codeGroupRenderer'],
                    true,
                ),
            ),
        );
        $questionPositions = array_keys(
            array_filter(
                $registeredProcessorIds,
                static fn(string $processorId): bool => in_array(
                    $processorId,
                    [QuestionProcessor::class, 'questionRenderer'],
                    true,
                ),
            ),
        );
        $oEmbedPositions = array_keys(
            array_filter(
                $registeredProcessorIds,
                static fn(string $processorId): bool => $processorId === OEmbedProcessor::class,
            ),
        );
        $markdownPosition = array_search(MarkdownProcessor::class, $registeredProcessorIds, true);
        $syntaxHighlightPosition = array_search(SyntaxHighlightProcessor::class, $registeredProcessorIds, true);
        self::assertNotFalse($markdownPosition);
        self::assertNotFalse($syntaxHighlightPosition);
        self::assertSame(
            [
                $markdownPosition - 2,
                $syntaxHighlightPosition + 1,
            ],
            $codeGroupPositions,
        );
        self::assertSame(
            [
                $markdownPosition - 1,
                $syntaxHighlightPosition + 2,
            ],
            $questionPositions,
        );
        self::assertSame(
            [
                $markdownPosition - 3,
                $markdownPosition + 1,
            ],
            $oEmbedPositions,
        );
        self::assertSame(
            $markdownPosition + 2,
            array_search(RootRelativeLinkProcessor::class, $registeredProcessorIds, true),
        );

        $pipeline = new ContentProcessorPipeline(new LatexMathProcessor());
        self::assertStringContainsString(
            'assets/plugins/latex-math.js',
            $pipeline->collectHeadAssets('<p><span class="math">x</span></p>'),
        );
    }
}

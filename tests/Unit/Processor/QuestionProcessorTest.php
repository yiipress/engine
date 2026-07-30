<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Processor\Question\QuestionProcessor;
use YiiPress\Render\MarkdownRenderer;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class QuestionProcessorTest extends TestCase
{
    public function testRendersInlineQuestionWithMarkdownAnswerAndEscapedTitle(): void
    {
        $result = $this->process(
            "Before.\n\n::: question Is <this> safe?\nUse **bold** and [a link](https://example.com).\n:::\n\nAfter.",
        );

        assertStringContainsString('<p>Before.</p>', $result);
        assertStringContainsString(
            '<details class="faq-question"><summary>Is &lt;this&gt; safe?</summary>'
            . '<div class="faq-answer"><p>Use <strong>bold</strong> and '
            . '<a href="https://example.com">a link</a>.</p></div></details>',
            $result,
        );
        assertStringContainsString('<p>After.</p>', $result);
        assertStringNotContainsString('::: question', $result);
    }

    public function testGroupsQuestionsAtPageEndInDocumentOrder(): void
    {
        $result = $this->process(
            "::: question First?\nFirst answer.\n:::\n\nMiddle.\n\n::: question Second?\nSecond answer.\n:::",
            0,
        );

        $section = '<section class="faq-section" aria-label="Frequently asked questions">';
        assertSame(1, substr_count($result, $section));
        self::assertLessThan(strpos($result, 'Second?'), strpos($result, 'First?'));
        self::assertLessThan(strpos($result, $section), strpos($result, '<p>Middle.</p>'));
    }

    public function testGroupsQuestionsAtEndOfMatchingHeadingSections(): void
    {
        $result = $this->process(
            "## Alpha\n\nAlpha text.\n\n::: question Alpha question?\nAlpha answer.\n:::\n\n"
            . "### Nested\n\nNested text.\n\n::: question Nested question?\nNested answer.\n:::\n\n"
            . "## Beta\n\nBeta text.\n\n::: question Beta question?\nBeta answer.\n:::",
            2,
        );

        assertSame(2, substr_count($result, 'class="faq-section"'));
        $alphaSection = strpos($result, 'class="faq-section"');
        $betaHeading = strpos($result, '>Beta</');
        self::assertIsInt($alphaSection);
        self::assertIsInt($betaHeading);
        self::assertLessThan($betaHeading, $alphaSection);
        self::assertLessThan(strpos($result, 'Nested question?'), strpos($result, 'Alpha question?'));
        self::assertLessThan(strpos($result, '>Beta</'), strpos($result, 'Nested question?'));
        self::assertLessThan(strpos($result, 'Beta question?'), strpos($result, '>Beta</'));
    }

    public function testLeavesNestedAndMalformedQuestionsAsLiteralContent(): void
    {
        $nested = $this->process(
            "::: question Outer?\n::: question Inner?\nAnswer.\n:::\n:::",
        );
        $shortClose = $this->process(
            ":::: question Long fence?\nAnswer.\n:::",
        );
        $unclosed = $this->process(
            "::: question Unclosed?\nAnswer.",
        );

        assertStringContainsString('question Outer?', $nested);
        assertStringContainsString('question Inner?', $nested);
        assertStringNotContainsString('class="faq-question"', $nested);
        assertStringContainsString('Long fence?', $shortClose);
        assertStringNotContainsString('class="faq-question"', $shortClose);
        assertStringContainsString('Unclosed?', $unclosed);
        assertStringNotContainsString('class="faq-question"', $unclosed);
    }

    public function testDoesNotInterpretQuestionSyntaxInsideCodeFences(): void
    {
        $result = $this->process(
            "```markdown\n::: question Example only?\nNot a real question.\n:::\n```",
        );

        assertStringContainsString('::: question Example only?', $result);
        assertStringNotContainsString('class="faq-question"', $result);
    }

    private function process(string $markdown, int|false|null $faqLevel = null): string
    {
        $questionProcessor = new QuestionProcessor();
        $pipeline = new ContentProcessorPipeline(
            $questionProcessor,
            new MarkdownProcessor(new MarkdownRenderer()),
            $questionProcessor,
        );

        return $pipeline->process($markdown, $this->createEntry($faqLevel));
    }

    private function createEntry(int|false|null $faqLevel): Entry
    {
        return new Entry(
            filePath: '',
            collection: 'docs',
            slug: 'faq',
            title: 'FAQ',
            date: new DateTimeImmutable('2026-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '',
            layout: '',
            theme: '',
            weight: 0,
            language: 'en',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: 0,
            faqLevel: $faqLevel,
        );
    }
}

<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use DateTimeImmutable;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Processor\Question\QuestionProcessor;
use YiiPress\Render\MarkdownRenderer;

final class QuestionProcessorBench
{
    private ContentProcessorPipeline $pipeline;
    private Entry $inlineEntry;
    private Entry $groupedEntry;
    private string $content;

    public function __construct()
    {
        $questionProcessor = new QuestionProcessor();
        $this->pipeline = new ContentProcessorPipeline(
            $questionProcessor,
            new MarkdownProcessor(new MarkdownRenderer()),
            $questionProcessor,
        );
        $this->inlineEntry = $this->entry(false);
        $this->groupedEntry = $this->entry(2);

        $sections = [];
        for ($section = 1; $section <= 10; $section++) {
            $questions = [];
            for ($question = 1; $question <= 10; $question++) {
                $questions[] = sprintf(
                    "::: question Question %d.%d?\nAnswer with **Markdown** and a [link](https://example.com).\n:::",
                    $section,
                    $question,
                );
            }
            $sections[] = "## Section $section\n\n" . implode("\n\n", $questions);
        }
        $this->content = implode("\n\n", $sections);
    }

    #[Revs(25)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchHundredInlineQuestions(): void
    {
        $this->pipeline->process($this->content, $this->inlineEntry);
    }

    #[Revs(25)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchHundredQuestionsGroupedBySection(): void
    {
        $this->pipeline->process($this->content, $this->groupedEntry);
    }

    private function entry(int|false $faqLevel): Entry
    {
        return new Entry(
            filePath: __FILE__,
            collection: 'docs',
            slug: 'faq-benchmark',
            title: 'FAQ benchmark',
            date: new DateTimeImmutable('2026-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '/docs/faq-benchmark/',
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

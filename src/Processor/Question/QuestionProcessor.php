<?php

declare(strict_types=1);

namespace YiiPress\Processor\Question;

use YiiPress\Content\Model\Entry;
use YiiPress\Processor\ContentProcessorInterface;

use function array_key_last;
use function array_slice;
use function base64_decode;
use function base64_encode;
use function count;
use function htmlspecialchars;
use function implode;
use function preg_match;
use function preg_match_all;
use function preg_replace_callback;
use function sprintf;
use function str_contains;
use function trim;

/**
 * Preserves question containers before Markdown and renders or groups them afterwards.
 */
final readonly class QuestionProcessor implements ContentProcessorInterface
{
    private const string START_MARKER = '<!-- yiipress-question:';
    private const string END_MARKER = '<!-- /yiipress-question -->';
    private const string QUESTION_PATTERN = '/<!-- yiipress-question:([A-Za-z0-9+\/=]+) -->\s*([\s\S]*?)\s*'
        . '<!-- \/yiipress-question -->/';

    public function process(string $content, Entry $entry): string
    {
        if (str_contains($content, self::START_MARKER)) {
            return $this->renderQuestions($content, $entry->faqLevel);
        }
        if (!str_contains($content, '::: question')) {
            return $content;
        }

        return $this->preserveQuestions($content);
    }

    private function preserveQuestions(string $content): string
    {
        $lines = preg_split('/\R/', $content);
        if ($lines === false) {
            return $content;
        }

        $output = [];
        $markdownFence = null;
        $suppressedQuestionFenceLength = null;
        for ($index = 0, $lineCount = count($lines); $index < $lineCount; $index++) {
            $line = $lines[$index];
            if ($suppressedQuestionFenceLength !== null) {
                $output[] = $line;
                if (
                    preg_match('/^(?<fence>:{3,})\s*$/', $line, $suppressedClose) === 1
                    && strlen($suppressedClose['fence']) >= $suppressedQuestionFenceLength
                ) {
                    $suppressedQuestionFenceLength = null;
                }
                continue;
            }
            if ($markdownFence !== null) {
                $output[] = $line;
                if ($this->isMarkdownFenceClose($line, $markdownFence)) {
                    $markdownFence = null;
                }
                continue;
            }
            $markdownFence = $this->markdownFence($line);
            if ($markdownFence !== null) {
                $output[] = $line;
                continue;
            }
            if (preg_match('/^(?<fence>:{3,})\s+question\s+(?<title>.+?)\s*$/', $line, $open) !== 1) {
                $output[] = $line;
                continue;
            }

            $answerFence = null;
            $closeIndex = null;
            $nested = false;
            for ($candidate = $index + 1; $candidate < $lineCount; $candidate++) {
                $answerLine = $lines[$candidate];
                if ($answerFence !== null) {
                    if ($this->isMarkdownFenceClose($answerLine, $answerFence)) {
                        $answerFence = null;
                    }
                    continue;
                }
                $answerFence = $this->markdownFence($answerLine);
                if ($answerFence !== null) {
                    continue;
                }
                if (preg_match('/^:{3,}\s+question(?:\s|$)/', $answerLine) === 1) {
                    $nested = true;
                    break;
                }
                if (
                    preg_match('/^(?<fence>:{3,})\s*$/', $answerLine, $close) === 1
                    && strlen($close['fence']) >= strlen($open['fence'])
                ) {
                    $closeIndex = $candidate;
                    break;
                }
            }

            if ($nested || $closeIndex === null) {
                $output[] = $line;
                if ($nested) {
                    $suppressedQuestionFenceLength = strlen($open['fence']);
                }
                continue;
            }

            $output[] = self::START_MARKER . base64_encode(trim($open['title'])) . ' -->';
            $output[] = trim(
                implode("\n", array_slice($lines, $index + 1, $closeIndex - $index - 1)),
                "\r\n",
            );
            $output[] = self::END_MARKER;
            $index = $closeIndex;
        }

        return implode("\n", $output);
    }

    /**
     * @return array{character: string, length: int}|null
     */
    private function markdownFence(string $line): ?array
    {
        if (preg_match('/^\s{0,3}(?<fence>`{3,}|~{3,})/', $line, $matches) !== 1) {
            return null;
        }

        return ['character' => $matches['fence'][0], 'length' => strlen($matches['fence'])];
    }

    /**
     * @param array{character: string, length: int} $fence
     */
    private function isMarkdownFenceClose(string $line, array $fence): bool
    {
        return preg_match(
            '/^\s{0,3}' . preg_quote($fence['character'], '/') . '{' . $fence['length'] . ',}\s*$/',
            $line,
        ) === 1;
    }

    private function renderQuestions(string $content, int|false|null $level): string
    {
        if ($level === false || $level === null) {
            return $this->renderInline($content);
        }

        $questions = [];
        $withoutQuestions = (string) preg_replace_callback(
            self::QUESTION_PATTERN,
            function (array $matches) use (&$questions): string {
                $questions[] = $this->details($matches[1], $matches[2]);

                return '<!-- yiipress-question-slot:' . array_key_last($questions) . ' -->';
            },
            $content,
        );

        if ($questions === []) {
            return $content;
        }
        if ($level === 0) {
            $withoutQuestions = (string) preg_replace_callback(
                '/<!-- yiipress-question-slot:\d+ -->/',
                static fn (): string => '',
                $withoutQuestions,
            );

            return rtrim($withoutQuestions) . "\n" . $this->section($questions);
        }

        return $this->groupByHeading($withoutQuestions, $questions, $level);
    }

    private function renderInline(string $content): string
    {
        return (string) preg_replace_callback(
            self::QUESTION_PATTERN,
            fn (array $matches): string => $this->details($matches[1], $matches[2]),
            $content,
        );
    }

    /**
     * @param list<string> $questions
     */
    private function groupByHeading(string $content, array $questions, int $level): string
    {
        preg_match_all(
            sprintf('/<h%d(?:\s[^>]*)?>.*?<\/h%d>|<!-- yiipress-question-slot:(\d+) -->/si', $level, $level),
            $content,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        $output = '';
        $offset = 0;
        $pending = [];
        foreach ($matches[0] as $index => [$match, $position]) {
            $output .= substr($content, $offset, $position - $offset);
            $questionIndex = $matches[1][$index][0];
            if ($questionIndex !== '') {
                $pending[] = $questions[(int) $questionIndex];
            } else {
                if ($pending !== []) {
                    $output .= $this->section($pending);
                    $pending = [];
                }
                $output .= $match;
            }
            $offset = $position + strlen($match);
        }
        $output .= substr($content, $offset);

        return $pending === [] ? $output : rtrim($output) . "\n" . $this->section($pending);
    }

    private function details(string $encodedTitle, string $answer): string
    {
        $title = base64_decode($encodedTitle, true);
        if ($title === false) {
            return '';
        }

        return sprintf(
            '<details class="faq-question"><summary>%s</summary><div class="faq-answer">%s</div></details>',
            htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5),
            trim($answer),
        );
    }

    /**
     * @param list<string> $questions
     */
    private function section(array $questions): string
    {
        return '<section class="faq-section" aria-label="Frequently asked questions">'
            . implode('', $questions)
            . '</section>';
    }

}

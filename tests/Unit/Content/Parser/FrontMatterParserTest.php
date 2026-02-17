<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Parser;

use App\Content\Parser\FrontMatterParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertArrayNotHasKey;
use function PHPUnit\Framework\assertGreaterThan;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class FrontMatterParserTest extends TestCase
{
    public function testParseFrontMatterWithAllFields(): void
    {
        $parser = new FrontMatterParser();
        $dataDir = dirname(__DIR__, 3) . '/Support/Data/content';

        $result = $parser->parse($dataDir . '/blog/2024-03-15-test-post.md');

        assertArrayHasKey('frontMatter', $result);
        assertArrayHasKey('bodyOffset', $result);
        assertArrayHasKey('bodyLength', $result);

        assertSame('Test Post', $result['frontMatter']['title']);
        assertSame(['php', 'testing'], $result['frontMatter']['tags']);
        assertSame(['tutorials'], $result['frontMatter']['categories']);
        assertSame(['john-doe'], $result['frontMatter']['authors']);
        assertSame('A test post summary.', $result['frontMatter']['summary']);
    }

    public function testBodyOffsetPointsAfterFrontMatter(): void
    {
        $parser = new FrontMatterParser();
        $dataDir = dirname(__DIR__, 3) . '/Support/Data/content';

        $result = $parser->parse($dataDir . '/blog/2024-03-15-test-post.md');

        assertGreaterThan(0, $result['bodyOffset']);
        assertGreaterThan(0, $result['bodyLength']);
    }

    public function testParseFrontMatterFromEntryFile(): void
    {
        $parser = new FrontMatterParser();
        $dataDir = dirname(__DIR__, 3) . '/Support/Data/content';

        $result = $parser->parse($dataDir . '/blog/no-date-post.md');

        assertSame('No Date Post', $result['frontMatter']['title']);
        assertSame('custom-slug', $result['frontMatter']['slug']);
        assertSame('2024-06-01', $result['frontMatter']['date']);
        assertSame(true, $result['frontMatter']['draft']);
    }

    public function testParseAuthorFile(): void
    {
        $parser = new FrontMatterParser();
        $dataDir = dirname(__DIR__, 3) . '/Support/Data/content';

        $result = $parser->parse($dataDir . '/authors/john-doe.md');

        assertSame('John Doe', $result['frontMatter']['title']);
        assertSame('john@example.com', $result['frontMatter']['email']);

        $body = file_get_contents($dataDir . '/authors/john-doe.md', offset: $result['bodyOffset'], length: $result['bodyLength']);
        assertStringContainsString('John is a PHP developer.', $body);
    }

    public function testInfersTitleFromH1WhenNoFrontMatter(): void
    {
        $tmpFile = $this->createTempFile("# My Page Title\n\nSome content.");

        $result = new FrontMatterParser()->parse($tmpFile);

        assertSame('My Page Title', $result['frontMatter']['title']);

        $body = file_get_contents($tmpFile, offset: $result['bodyOffset'], length: $result['bodyLength']);
        assertSame("\nSome content.", $body);
    }

    public function testInfersTitleFromH1WhenFrontMatterHasNoTitle(): void
    {
        $tmpFile = $this->createTempFile("---\ndraft: true\n---\n\n# Inferred Title\n\nBody.");

        $result = new FrontMatterParser()->parse($tmpFile);

        assertSame('Inferred Title', $result['frontMatter']['title']);
        assertSame(true, $result['frontMatter']['draft']);

        $body = file_get_contents($tmpFile, offset: $result['bodyOffset'], length: $result['bodyLength']);
        assertSame("\nBody.", $body);
    }

    public function testInfersTitleFromH1AfterBlankLine(): void
    {
        $tmpFile = $this->createTempFile("\n# Title After Blank\n\nBody.");

        $result = new FrontMatterParser()->parse($tmpFile);

        assertSame('Title After Blank', $result['frontMatter']['title']);

        $body = file_get_contents($tmpFile, offset: $result['bodyOffset'], length: $result['bodyLength']);
        assertSame("\nBody.", $body);
    }

    public function testNoTitleWhenNoFrontMatterAndNoHeading(): void
    {
        $tmpFile = $this->createTempFile("Just some text without a heading.");

        $result = new FrontMatterParser()->parse($tmpFile);

        assertArrayNotHasKey('title', $result['frontMatter']);
    }

    public function testNoTitleInferenceWhenFirstNonBlankLineIsNotH1(): void
    {
        $tmpFile = $this->createTempFile("Some paragraph.\n# Heading");

        $result = new FrontMatterParser()->parse($tmpFile);

        assertArrayNotHasKey('title', $result['frontMatter']);
    }

    public function testFrontMatterTitleTakesPrecedenceOverH1(): void
    {
        $tmpFile = $this->createTempFile("---\ntitle: Explicit Title\n---\n\n# H1 Title\n\nBody.");

        $result = new FrontMatterParser()->parse($tmpFile);

        assertSame('Explicit Title', $result['frontMatter']['title']);
    }

    private function createTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'yiipress_fm_test_');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
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

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Parser;

use App\Content\Parser\FrontMatterParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertArrayHasKey;
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
}

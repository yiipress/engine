<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Parser;

use App\Content\Parser\FilenameParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

final class FilenameParserTest extends TestCase
{
    public function testFilenameWithDate(): void
    {
        $parser = new FilenameParser();

        $result = $parser->parse('2024-03-15-hello-world.md');

        assertSame('2024-03-15', $result['date']->format('Y-m-d'));
        assertSame('hello-world', $result['slug']);
    }

    public function testFilenameWithoutDate(): void
    {
        $parser = new FilenameParser();

        $result = $parser->parse('about.md');

        assertNull($result['date']);
        assertSame('about', $result['slug']);
    }

    public function testFilenameWithPathAndDate(): void
    {
        $parser = new FilenameParser();

        $result = $parser->parse('/content/blog/2024-01-01-new-year.md');

        assertSame('2024-01-01', $result['date']->format('Y-m-d'));
        assertSame('new-year', $result['slug']);
    }

    public function testFilenameWithPathWithoutDate(): void
    {
        $parser = new FilenameParser();

        $result = $parser->parse('/content/page/contact.md');

        assertNull($result['date']);
        assertSame('contact', $result['slug']);
    }
}

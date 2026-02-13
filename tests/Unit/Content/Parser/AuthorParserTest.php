<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Parser;

use App\Content\Parser\AuthorParser;
use App\Content\Parser\FrontMatterParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class AuthorParserTest extends TestCase
{
    public function testParseAuthor(): void
    {
        $parser = new AuthorParser(new FrontMatterParser());
        $dataDir = dirname(__DIR__, 3) . '/Support/Data/content';

        $author = $parser->parse($dataDir . '/authors/john-doe.md');

        assertSame('john-doe', $author->slug);
        assertSame('John Doe', $author->title);
        assertSame('john@example.com', $author->email);
        assertSame('https://johndoe.com', $author->url);
        assertSame('/authors/assets/john-doe.svg', $author->avatar);
    }

    public function testAuthorBodyIsLoadedLazily(): void
    {
        $parser = new AuthorParser(new FrontMatterParser());
        $dataDir = dirname(__DIR__, 3) . '/Support/Data/content';

        $author = $parser->parse($dataDir . '/authors/john-doe.md');

        assertStringContainsString('John is a PHP developer.', $author->body());
    }
}

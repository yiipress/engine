<?php

declare(strict_types=1);

namespace App\Content\Parser;

use App\Content\Model\Author;

final readonly class AuthorParser
{
    public function __construct(
        private FrontMatterParser $frontMatterParser,
    ) {}

    public function parse(string $filePath): Author
    {
        $slug = basename($filePath, '.md');
        $result = $this->frontMatterParser->parse($filePath);
        $fields = $result['frontMatter'];

        return new Author(
            slug: $slug,
            title: (string) ($fields['title'] ?? $slug),
            email: (string) ($fields['email'] ?? ''),
            url: (string) ($fields['url'] ?? ''),
            avatar: (string) ($fields['avatar'] ?? ''),
            bodyOffset: $result['bodyOffset'],
            bodyLength: $result['bodyLength'],
            filePath: $filePath,
        );
    }
}

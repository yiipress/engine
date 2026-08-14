<?php

declare(strict_types=1);

namespace YiiPress\Content\Parser;

use YiiPress\Content\Model\Author;

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
            title: ValueNormalizer::string($fields['title'] ?? null, $slug),
            email: ValueNormalizer::string($fields['email'] ?? null),
            url: ValueNormalizer::string($fields['url'] ?? null),
            avatar: ValueNormalizer::string($fields['avatar'] ?? null),
            bodyOffset: $result['bodyOffset'],
            bodyLength: $result['bodyLength'],
            filePath: $filePath,
        );
    }
}

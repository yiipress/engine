<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Content\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Content\Model\Entry;

use function PHPUnit\Framework\assertSame;

final class EntryRedirectTest extends TestCase
{
    #[DataProvider('lastUpdatedProvider')]
    public function testWithRedirectToPreservesLastUpdatedOverride(?bool $lastUpdated): void
    {
        $entry = new Entry(
            filePath: '/content/post.md',
            collection: 'blog',
            slug: 'post',
            title: 'Post',
            date: null,
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '',
            layout: '',
            theme: '',
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: 0,
            lastUpdated: $lastUpdated,
        );

        $redirect = $entry->withRedirectTo('/new-location/');

        assertSame('/new-location/', $redirect->redirectTo);
        assertSame($lastUpdated, $redirect->lastUpdated);
    }

    public static function lastUpdatedProvider(): iterable
    {
        yield 'enabled' => [true];
        yield 'disabled' => [false];
        yield 'inherited' => [null];
    }
}

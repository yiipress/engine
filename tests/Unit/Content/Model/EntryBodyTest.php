<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Model;

use App\Content\Model\Entry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class EntryBodyTest extends TestCase
{
    public function testBodyIsLoadedOncePerEntryInstance(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress_entry_body_');
        file_put_contents($file, "First body.");

        try {
            $entry = new Entry(
                filePath: $file,
                collection: 'blog',
                slug: 'test',
                title: 'Test',
                date: new DateTimeImmutable('2024-01-01'),
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
                bodyLength: strlen('First body.'),
            );

            assertSame('First body.', $entry->body());

            file_put_contents($file, 'Second body');

            assertSame('First body.', $entry->body());
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

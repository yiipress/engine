<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Content;

use YiiPress\Content\Slugifier;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class SlugifierTest extends TestCase
{
    public function testSlugifiesAsciiTitle(): void
    {
        assertSame('hello-from-telegram-channel', Slugifier::slugify('Hello from Telegram channel'));
    }

    public function testPreservesUnicodeLetters(): void
    {
        assertSame('привет-мир', Slugifier::slugify('Привет, мир!'));
    }

    public function testAppliesMaxLengthWithoutTrailingSeparator(): void
    {
        assertSame('very-long', Slugifier::slugify('Very long title', maxLength: 10));
    }

    public function testUsesFallbackForEmptySlug(): void
    {
        assertSame('post', Slugifier::slugify('!!!', fallback: 'post'));
    }
}

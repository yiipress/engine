<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Content\Parser;

use PHPUnit\Framework\TestCase;
use YiiPress\Content\Parser\ValueNormalizer;

final class ValueNormalizerTest extends TestCase
{
    public function testNormalizesScalarValues(): void
    {
        self::assertSame('42', ValueNormalizer::string(42));
        self::assertSame('fallback', ValueNormalizer::string([], 'fallback'));
        self::assertSame(42, ValueNormalizer::int('42'));
        self::assertSame(7, ValueNormalizer::int([], 7));
    }

    public function testNormalizesListsAndMaps(): void
    {
        self::assertSame(['one', '2'], ValueNormalizer::stringList(['one', [], 2]));
        self::assertSame(['name' => 'YiiPress'], ValueNormalizer::map(['name' => 'YiiPress', 0 => 'ignored']));
    }
}

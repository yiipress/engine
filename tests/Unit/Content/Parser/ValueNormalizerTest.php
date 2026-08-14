<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Content\Parser;

use PHPUnit\Framework\TestCase;
use YiiPress\Content\Parser\ValueNormalizer;

use function PHPUnit\Framework\assertSame;

final class ValueNormalizerTest extends TestCase
{
    public function testRejectsNestedValuesForScalarFields(): void
    {
        assertSame('fallback', ValueNormalizer::string(['nested'], 'fallback'));
    }

    public function testFiltersNestedListValues(): void
    {
        assertSame(['one', '2'], ValueNormalizer::stringList(['one', ['nested'], 2]));
    }

    public function testKeepsOnlyStringKeysInMaps(): void
    {
        assertSame(['key' => 'value'], ValueNormalizer::map(['key' => 'value', 0 => 'ignored']));
    }
}

<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\ExtensionId;

use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

final class ExtensionIdTest extends TestCase
{
    public function testParsesVendorAndName(): void
    {
        $id = ExtensionId::tryFromString('yiipress/wonder-theme');

        assertNotNull($id);
        assertSame('yiipress', $id->vendor);
        assertSame('wonder-theme', $id->name);
        assertSame('yiipress/wonder-theme', (string) $id);
    }

    public function testAcceptsPartOfSixtyFourCharacters(): void
    {
        assertNotNull(ExtensionId::tryFromString(str_repeat('a', 64) . '/b1'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIdDataProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'no slash' => ['wonder'];
        yield 'three parts' => ['a/b/c'];
        yield 'empty vendor' => ['/wonder'];
        yield 'empty name' => ['vendor/'];
        yield 'uppercase' => ['Vendor/wonder'];
        yield 'underscore' => ['vendor/wonder_theme'];
        yield 'leading hyphen' => ['vendor/-wonder'];
        yield 'trailing hyphen' => ['vendor/wonder-'];
        yield 'double hyphen' => ['vendor/won--der'];
        yield 'dot' => ['vendor/wonder.theme'];
        yield 'too long part' => [str_repeat('a', 65) . '/b'];
        yield 'reserved con' => ['con/wonder'];
        yield 'reserved nul' => ['vendor/nul'];
        yield 'reserved com1' => ['vendor/com1'];
        yield 'reserved lpt9' => ['lpt9/wonder'];
        yield 'reserved com0' => ['vendor/com0'];
    }

    #[DataProvider('invalidIdDataProvider')]
    public function testRejectsInvalidId(string $value): void
    {
        assertNull(ExtensionId::tryFromString($value));
    }
}

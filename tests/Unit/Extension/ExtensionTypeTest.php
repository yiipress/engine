<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\ExtensionType;

use function PHPUnit\Framework\assertSame;

final class ExtensionTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{ExtensionType, bool}>
     */
    public static function supportDataProvider(): iterable
    {
        yield 'theme' => [ExtensionType::Theme, true];
        yield 'processor' => [ExtensionType::Processor, true];
        yield 'importer' => [ExtensionType::Importer, false];
        yield 'plugin' => [ExtensionType::Plugin, false];
    }

    #[DataProvider('supportDataProvider')]
    public function testIsSupportedOnlyForThemesAndProcessors(ExtensionType $type, bool $expected): void
    {
        assertSame($expected, $type->isSupported());
    }

    public function testValuesAreManifestWords(): void
    {
        assertSame(['theme', 'processor', 'importer', 'plugin'], array_column(ExtensionType::cases(), 'value'));
    }
}

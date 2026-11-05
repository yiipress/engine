<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Processor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\Processor\LockedProcessorDestination;
use YiiPress\Tests\Support\ProcessorFixture;

final class LockedProcessorDestinationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}> Destination, content directory.
     */
    public static function trustedDestinations(): iterable
    {
        yield 'default content directory' => ['content/extensions/processors/acme/toc', 'content'];
        yield 'nested content directory' => ['site/content/extensions/processors/acme/toc', 'site/content'];
        yield 'project root' => ['extensions/processors/acme/toc', '.'];
    }

    #[DataProvider('trustedDestinations')]
    public function testTrustsDestinationOfItsId(string $destination, string $contentDir): void
    {
        $locked = ProcessorFixture::locked($destination);

        self::assertNull(LockedProcessorDestination::problem('acme/toc', $locked));
        self::assertSame($contentDir, LockedProcessorDestination::contentDir($locked));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function untrustedDestinations(): iterable
    {
        yield 'another ID' => ['content/extensions/processors/acme/other'];
        yield 'another vendor' => ['content/extensions/processors/evil/toc'];
        yield 'theme directory' => ['themes/toc'];
        yield 'discovered processors' => ['content/processors/acme/toc'];
        yield 'parent segment' => ['../extensions/processors/acme/toc'];
        yield 'state directory' => ['.yiipress/extensions/processors/acme/toc'];
        yield 'absolute' => ['/srv/extensions/processors/acme/toc'];
        yield 'below the package' => ['content/extensions/processors/acme/toc/src'];
    }

    #[DataProvider('untrustedDestinations')]
    public function testRefusesOtherDestinations(string $destination): void
    {
        $problem = LockedProcessorDestination::problem('acme/toc', ProcessorFixture::locked($destination));

        self::assertSame('lock_invalid', $problem?->errorCode);
        self::assertStringContainsString('extensions.acme/toc.destination', $problem->getMessage());
    }
}

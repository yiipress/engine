<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use YiiPress\Console\SelfUpdateCommand;
use YiiPress\Update\PackageLocator;
use YiiPress\Update\ReleaseClient;
use YiiPress\Update\SelfUpdater;

final class SelfUpdateCommandTest extends TestCase
{
    #[Test]
    public function commandOffersNightlyOption(): void
    {
        $command = new SelfUpdateCommand(new SelfUpdater(new PackageLocator(), new ReleaseClient()));

        self::assertTrue($command->getDefinition()->hasOption('nightly'));
        self::assertSame('self-update', $command->getName());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Packaging;

use App\Console\BuildCommand;
use App\Console\CleanCommand;
use App\Console\ImportCommand;
use App\Console\NewCommand;
use App\Console\ServeCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chdir;
use function getcwd;
use function mkdir;
use function PHPUnit\Framework\assertSame;

final class ConfigurationPackagingTest extends TestCase
{
    #[Test]
    public function applicationConfigurationDoesNotUseFileGlobs(): void
    {
        $configuration = require dirname(__DIR__, 3) . '/config/configuration.php';
        $paths = $this->collectStrings($configuration['config-plugin'] ?? []);

        foreach ($paths as $path) {
            self::assertStringNotContainsString(
                '*',
                $path,
                'PHAR builds need explicit config file paths because glob expansion is not portable inside archives.',
            );
        }
    }

    #[Test]
    public function consoleCommandRootPathsUseCurrentWorkingDirectory(): void
    {
        $previousDirectory = getcwd();
        self::assertIsString($previousDirectory);

        $workingDirectory = sys_get_temp_dir() . '/yiipress-packaging-cwd-' . uniqid();
        mkdir($workingDirectory);

        try {
            chdir($workingDirectory);

            $contentPipelineConfiguration = require dirname(__DIR__, 3) . '/config/common/di/content-pipeline.php';
            $importerConfiguration = require dirname(__DIR__, 3) . '/config/common/di/importer.php';

            assertSame($workingDirectory, $contentPipelineConfiguration[BuildCommand::class]['__construct()']['rootPath']);
            assertSame($workingDirectory, $contentPipelineConfiguration[CleanCommand::class]['__construct()']['rootPath']);
            assertSame($workingDirectory, $contentPipelineConfiguration[NewCommand::class]['__construct()']['rootPath']);
            assertSame($workingDirectory, $importerConfiguration[ImportCommand::class]['__construct()']['rootPath']);
        } finally {
            chdir($previousDirectory);
            rmdir($workingDirectory);
        }
    }

    #[Test]
    public function serveCommandIsPackageAware(): void
    {
        $commands = require dirname(__DIR__, 3) . '/config/console/commands.php';

        /** @psalm-suppress RedundantCondition */
        assertSame(ServeCommand::class, $commands['serve']);
    }

    #[Test]
    public function staticBinaryBuildDoesNotRequireNativeSocketsExtension(): void
    {
        $root = dirname(__DIR__, 3);
        $dockerfile = file_get_contents($root . '/docker/Dockerfile');
        $registrationPatch = file_get_contents($root . '/build/static-php/register-yiipress-highlighter.php');

        self::assertIsString($dockerfile);
        self::assertIsString($registrationPatch);
        self::assertStringNotContainsString('sockets', $dockerfile);
        self::assertStringNotContainsString('php_sockets', $registrationPatch);
        self::assertStringNotContainsString('sockets_module_entry', $registrationPatch);
    }

    /**
     * @return string[]
     */
    private function collectStrings(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            array_push($strings, ...$this->collectStrings($item));
        }

        return $strings;
    }
}

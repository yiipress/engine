<?php

declare(strict_types=1);

namespace App\Tests\Unit\Packaging;

use App\Console\BuildCommand;
use App\Console\CleanCommand;
use App\Console\ImportCommand;
use App\Console\NewCommand;
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

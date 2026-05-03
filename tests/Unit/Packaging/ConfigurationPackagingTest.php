<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Packaging;

use YiiPress\Console\BuildCommand;
use YiiPress\Console\CleanCommand;
use YiiPress\Console\ImportCommand;
use YiiPress\Console\NewCommand;
use YiiPress\Console\ServeCommand;
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

    #[Test]
    public function staticBinaryBuildTrimsUnusedServerSource(): void
    {
        $root = dirname(__DIR__, 3);
        $workingDirectory = sys_get_temp_dir() . '/yiipress-source-config-' . uniqid();
        mkdir($workingDirectory);

        $unusedServerSource = 'fr' . 'an' . 'ken' . 'php';
        $sourceConfig = $workingDirectory . '/source.json';
        $libraryConfig = $workingDirectory . '/lib.json';

        try {
            file_put_contents(
                $sourceConfig,
                json_encode(
                    [
                        $unusedServerSource => ['type' => 'url'],
                        'curl' => ['repo' => 'curl/curl', 'match' => '^curl-(.*)$', 'prefer-stable' => true, 'alt' => []],
                        'icu' => ['repo' => 'unicode-org/icu', 'match' => '^release-(.*)$', 'prefer-stable' => true, 'alt' => []],
                        'libyaml' => ['repo' => 'yaml/libyaml', 'match' => '^(.*)$', 'prefer-stable' => true, 'alt' => []],
                        'openssl' => ['repo' => 'openssl/openssl', 'match' => '^openssl-(.*)$', 'prefer-stable' => true, 'alt' => []],
                        'zlib' => ['repo' => 'madler/zlib', 'match' => '^(.*)$', 'prefer-stable' => true, 'alt' => []],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            );
            file_put_contents(
                $libraryConfig,
                json_encode(
                    [
                        'php' => [
                            'lib-depends' => ['lib-base', 'micro', $unusedServerSource],
                            'lib-depends-macos' => ['lib-base', 'micro', 'libxml2', $unusedServerSource],
                        ],
                        'micro' => ['type' => 'target'],
                        $unusedServerSource => ['source' => $unusedServerSource, 'type' => 'target'],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            );

            /** @var list<string> $output */
            $output = [];
            $exitCode = 1;
            exec(
                escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg($root . '/build/static-php/patch-source-config.php') . ' '
                . escapeshellarg($sourceConfig) . ' '
                . escapeshellarg($libraryConfig),
                $output,
                $exitCode,
            );
            $sources = json_decode((string) file_get_contents($sourceConfig), true, flags: JSON_THROW_ON_ERROR);
            $libraries = json_decode((string) file_get_contents($libraryConfig), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($sources);
            self::assertIsArray($libraries);
            self::assertIsArray($libraries['php']);
            self::assertIsArray($libraries['php']['lib-depends']);
            self::assertIsArray($libraries['php']['lib-depends-macos']);

            self::assertSame(0, $exitCode);
            self::assertArrayNotHasKey($unusedServerSource, $sources);
            self::assertArrayNotHasKey($unusedServerSource, $libraries);
            self::assertNotContains($unusedServerSource, $libraries['php']['lib-depends']);
            self::assertNotContains($unusedServerSource, $libraries['php']['lib-depends-macos']);
        } finally {
            if (is_file($sourceConfig)) {
                unlink($sourceConfig);
            }
            if (is_file($libraryConfig)) {
                unlink($libraryConfig);
            }
            rmdir($workingDirectory);
        }
    }

    #[Test]
    public function pharBuilderCopiesOnlyRuntimeInputs(): void
    {
        $dockerfile = file_get_contents(dirname(__DIR__, 3) . '/docker/Dockerfile');
        $packageScript = file_get_contents(dirname(__DIR__, 3) . '/build/package-phar.php');
        self::assertIsString($dockerfile);
        self::assertIsString($packageScript);

        $start = strpos($dockerfile, 'FROM app-base AS phar-builder');
        $end = strpos($dockerfile, 'FROM app-base AS static-package');

        self::assertIsInt($start);
        self::assertIsInt($end);

        $stage = substr($dockerfile, $start, $end - $start);

        self::assertStringNotContainsString('COPY . /app', $stage);
        self::assertStringNotContainsString('COPY content /app/content', $stage);
        self::assertStringNotContainsString("'content'", $packageScript);
        self::assertStringContainsString('COPY config /app/config', $stage);
        self::assertStringContainsString('COPY public /app/public', $stage);
        self::assertStringContainsString('COPY src /app/src', $stage);
        self::assertStringContainsString('COPY themes /app/themes', $stage);
        self::assertStringContainsString('COPY build/package-phar.php /app/build/', $stage);
        self::assertStringContainsString('COPY yii composer.json composer.lock /app/', $stage);
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

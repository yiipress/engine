<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Packaging;

use YiiPress\Console\BuildCommand;
use YiiPress\Console\CleanCommand;
use YiiPress\Console\InitCommand;
use YiiPress\Console\ImportCommand;
use YiiPress\Console\NewCommand;
use YiiPress\Console\ServeCommand;
use YiiPress\Build\PharArchiveFilter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chdir;
use function getcwd;
use function mkdir;
use function PHPUnit\Framework\assertSame;

require_once dirname(__DIR__, 3) . '/build/PharArchiveFilter.php';

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
            assertSame($workingDirectory, $contentPipelineConfiguration[InitCommand::class]['__construct()']['rootPath']);
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
        assertSame(InitCommand::class, $commands['init']);
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
        self::assertStringContainsString("DIRECTORY_SEPARATOR === '\\\\'", $registrationPatch);
    }

    #[Test]
    public function makefileExposesAllPackageBuilds(): void
    {
        $makefile = file_get_contents(dirname(__DIR__, 3) . '/Makefile');
        self::assertIsString($makefile);

        self::assertStringContainsString('package-phar:', $makefile);
        self::assertStringContainsString('--target package-phar-artifacts', $makefile);
        self::assertStringContainsString('package-linux:', $makefile);
        self::assertStringContainsString('--target package-linux-artifacts', $makefile);
        self::assertStringContainsString('rm -f $(PACKAGE_LINUX_DIST)/yiipress.phar', $makefile);
        self::assertStringContainsString('package-windows:', $makefile);
        self::assertStringContainsString('build/package-windows.ps1', $makefile);
        self::assertStringContainsString('PowerShell 7 (pwsh) is required for package-windows.', $makefile);
        self::assertStringContainsString('package-distroless:', $makefile);
        self::assertStringContainsString('--target distroless', $makefile);
        self::assertStringContainsString('package-distroless-push:', $makefile);
        self::assertStringContainsString('--push -t $(PACKAGE_IMAGE):$(IMAGE_TAG)', $makefile);
    }

    #[Test]
    public function distrolessImageCopiesOnlyStaticBinary(): void
    {
        $dockerfile = file_get_contents(dirname(__DIR__, 3) . '/docker/Dockerfile');
        self::assertIsString($dockerfile);

        $start = strpos($dockerfile, 'FROM gcr.io/distroless/static-debian12:nonroot AS distroless');
        self::assertIsInt($start);
        $stage = substr($dockerfile, $start);

        self::assertStringContainsString('COPY --from=static-package /artifacts/yiipress /yiipress', $stage);
        self::assertStringContainsString('org.opencontainers.image.title="YiiPress"', $stage);
        self::assertStringContainsString('org.opencontainers.image.description="YiiPress static website builder"', $stage);
        self::assertStringContainsString('ENTRYPOINT ["/yiipress"]', $stage);
        self::assertStringNotContainsString('COPY --from=static-package /artifacts/yiipress.phar', $stage);
        self::assertStringNotContainsString('micro.sfx', $stage);
        self::assertStringNotContainsString('/bin/sh', $stage);
        self::assertStringNotContainsString('apt-get', $stage);
    }

    #[Test]
    public function platformArtifactsDoNotIncludeStandalonePhar(): void
    {
        $dockerfile = file_get_contents(dirname(__DIR__, 3) . '/docker/Dockerfile');
        $script = file_get_contents(dirname(__DIR__, 3) . '/build/package-windows.ps1');
        self::assertIsString($dockerfile);
        self::assertIsString($script);

        $pharStart = strpos($dockerfile, 'FROM scratch AS package-phar-artifacts');
        $linuxStart = strpos($dockerfile, 'FROM scratch AS package-linux-artifacts');
        $aliasStart = strpos($dockerfile, 'FROM package-linux-artifacts AS package-artifacts');

        self::assertIsInt($pharStart);
        self::assertIsInt($linuxStart);
        self::assertIsInt($aliasStart);

        $pharStage = substr($dockerfile, $pharStart, $linuxStart - $pharStart);
        $linuxStage = substr($dockerfile, $linuxStart, $aliasStart - $linuxStart);

        self::assertStringContainsString('COPY --from=phar-builder /app/dist/yiipress.phar /yiipress.phar', $pharStage);
        self::assertStringContainsString('COPY --from=static-package /artifacts/yiipress /yiipress', $linuxStage);
        self::assertStringNotContainsString('yiipress.phar', $linuxStage);
        self::assertStringContainsString('$pharPath = Join-Path $workPath "yiipress.phar"', $script);
        self::assertStringNotContainsString('$pharPath = Join-Path $distPath "yiipress.phar"', $script);
        self::assertStringContainsString('$legacyDistPharPath = Join-Path $distPath "yiipress.phar"', $script);
        self::assertStringContainsString('Remove-Item $legacyDistPharPath -Force', $script);
    }

    #[Test]
    public function windowsPackageScriptBuildsPharAndExecutable(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3) . '/build/package-windows.ps1');
        self::assertIsString($script);

        self::assertStringContainsString('build/package-phar.php', $script);
        self::assertStringContainsString('yiipress.exe', $script);
        self::assertStringContainsString('x86_64-pc-windows-msvc', $script);
        self::assertStringContainsString('micro:combine', $script);
        self::assertStringContainsString('$appPath = Join-Path $workPath "app"', $script);
        self::assertStringContainsString('Push-Location $appPath', $script);
        self::assertStringContainsString('function Test-NativeCommand', $script);
        self::assertStringContainsString('foreach ($command in @("php", "composer", "tar", "rustup", "cargo"))', $script);
        self::assertStringContainsString('foreach ($command in @("cl", "nmake"))', $script);
        self::assertStringNotContainsString('if ($IsWindows)', $script);
        self::assertStringContainsString('Invoke-WebRequest -Uri $Url -OutFile $archive -TimeoutSec 300', $script);
        self::assertStringContainsString('finally {', $script);
        self::assertStringContainsString('[string] $HighlighterVersion = "1.0.1"', $script);
        self::assertStringNotContainsString('"dev-master"', $script);
        self::assertStringContainsString('--ignore-platform-req=ext-inotify', $script);
        self::assertStringContainsString('--ignore-platform-req=ext-pcntl', $script);
        self::assertStringContainsString('--ignore-platform-req=ext-posix', $script);
        self::assertStringContainsString('function Write-LogTail', $script);
        self::assertStringContainsString('log/spc.output.log', $script);
        self::assertStringContainsString('log/spc.shell.log', $script);
        self::assertStringNotContainsString('--with-micro-fake-cli', $script);
        self::assertStringContainsString('buildroot/lib', $script);
        self::assertStringContainsString('highlighter.lib', $script);
        self::assertStringContainsString('$env:RUSTFLAGS = "-C target-feature=+crt-static"', $script);
    }

    #[Test]
    public function packageWorkflowPublishesNightlyAndReleaseBuilds(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 3) . '/.github/workflows/package-static.yml');
        self::assertIsString($workflow);

        self::assertStringContainsString('target: package-phar-artifacts', $workflow);
        self::assertStringContainsString('name: yiipress-phar', $workflow);
        self::assertStringContainsString('path: dist/phar/yiipress.phar', $workflow);
        self::assertStringContainsString('target: package-linux-artifacts', $workflow);
        self::assertStringContainsString('Smoke test Linux binary', $workflow);
        self::assertStringContainsString('./dist/linux-amd64/yiipress --help', $workflow);
        self::assertStringContainsString('path: dist/linux-amd64/yiipress', $workflow);
        self::assertStringContainsString('build/package-windows.ps1 -DistDir dist/windows-amd64', $workflow);
        self::assertStringContainsString(
            "github.event_name != 'pull_request' || github.event.pull_request.head.repo.full_name == github.repository",
            $workflow,
        );
        self::assertStringContainsString('Cache Windows package dependencies', $workflow);
        self::assertStringContainsString('runtime\package-windows\yiipress-highlighter', $workflow);
        self::assertStringContainsString('Smoke test Windows binary', $workflow);
        self::assertStringContainsString('./dist/windows-amd64/yiipress.exe --help', $workflow);
        self::assertStringContainsString('path: dist/windows-amd64/yiipress.exe', $workflow);
        self::assertStringContainsString('target: distroless', $workflow);
        self::assertStringContainsString("github.ref == 'refs/heads/master' || startsWith(github.ref, 'refs/tags/')", $workflow);
        self::assertStringContainsString('type=raw,value=nightly', $workflow);
        self::assertStringContainsString('type=semver,pattern={{version}}', $workflow);
        self::assertStringContainsString('cp release/yiipress-phar/yiipress.phar yiipress.phar', $workflow);
        self::assertStringContainsString('softprops/action-gh-release', $workflow);
        self::assertDoesNotMatchRegularExpression('/uses:\s+[^@\s]+@v\d+/', $workflow);
        self::assertStringNotContainsString('dtolnay/rust-toolchain@stable', $workflow);
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
        self::assertStringNotContainsString('COPY runtime /app/runtime', $stage);
        self::assertStringNotContainsString("'content'", $packageScript);
        self::assertStringNotContainsString("'runtime'", $packageScript);
        self::assertStringContainsString('PharArchiveFilter::shouldExclude($localPath)', $packageScript);
        self::assertStringNotContainsString('packages/highlighter-extension/php', $stage);
        self::assertStringNotContainsString("'packages/highlighter-extension/php'", $packageScript);
        self::assertStringContainsString('COPY config /app/config', $stage);
        self::assertStringContainsString('COPY public /app/public', $stage);
        self::assertStringContainsString('COPY src /app/src', $stage);
        self::assertStringContainsString('COPY themes /app/themes', $stage);
        self::assertStringContainsString('COPY build/package-phar.php build/PharArchiveFilter.php /app/build/', $stage);
        self::assertStringContainsString('COPY yii composer.json composer.lock /app/', $stage);
    }

    #[Test]
    public function staticPackageInstallsRustTargetForHighlighterToolchain(): void
    {
        $dockerfile = file_get_contents(dirname(__DIR__, 3) . '/docker/Dockerfile');
        $windowsScript = file_get_contents(dirname(__DIR__, 3) . '/build/package-windows.ps1');
        self::assertIsString($dockerfile);
        self::assertIsString($windowsScript);

        self::assertStringContainsString('RUN rustup target add x86_64-unknown-linux-musl', $dockerfile);
        self::assertStringContainsString(
            'RUN cd /opt/yiipress-highlighter && rustup target add x86_64-unknown-linux-musl',
            $dockerfile,
        );
        self::assertStringContainsString('Invoke-NativeCommand "rustup" @("target", "add", $env:CARGO_BUILD_TARGET)', $windowsScript);
    }

    #[Test]
    public function dockerPackageBuildUsesComposerAndCargoCaches(): void
    {
        $dockerfile = file_get_contents(dirname(__DIR__, 3) . '/docker/Dockerfile');
        self::assertIsString($dockerfile);

        self::assertStringContainsString('--mount=type=cache,target=/tmp/composer-cache', $dockerfile);
        self::assertStringContainsString('COMPOSER_CACHE_DIR=/tmp/composer-cache composer install', $dockerfile);
        self::assertStringContainsString('COMPOSER_CACHE_DIR=/tmp/composer-cache composer create-project', $dockerfile);
        self::assertStringContainsString('ARG HIGHLIGHTER_VERSION=1.0.1', $dockerfile);
        self::assertStringNotContainsString('yiipress/highlighter /build/highlighter-extension dev-master', $dockerfile);
        self::assertStringNotContainsString('yiipress/highlighter /opt/yiipress-highlighter dev-master', $dockerfile);
        self::assertStringContainsString('--mount=type=cache,target=/root/.cargo/git', $dockerfile);
        self::assertStringContainsString('--mount=type=cache,target=/root/.cargo/registry', $dockerfile);
    }

    #[Test]
    public function customStaticExtensionsAreAvailableForWindowsBuilds(): void
    {
        $extensionConfigPatch = file_get_contents(dirname(__DIR__, 3) . '/build/static-php/patch-extension-config.php');
        $registrationPatch = file_get_contents(dirname(__DIR__, 3) . '/build/static-php/register-yiipress-highlighter.php');
        self::assertIsString($extensionConfigPatch);
        self::assertIsString($registrationPatch);

        self::assertStringContainsString("'highlighter'", $extensionConfigPatch);
        self::assertStringContainsString("'md4c'", $extensionConfigPatch);
        self::assertStringNotContainsString("'unix-only' => true", $extensionConfigPatch);
        self::assertStringContainsString("patch_point() !== 'after-exts-extract'", $registrationPatch);
        self::assertStringContainsString('ARG_ENABLE("highlighter"', $registrationPatch);
        self::assertStringContainsString('ARG_ENABLE("md4c"', $registrationPatch);
        self::assertStringContainsString('EXTENSION("highlighter", "highlighter.c", false);', $registrationPatch);
        self::assertStringContainsString('$highlighterWindowsConfigReplacementCount !== 1', $registrationPatch);
        self::assertStringContainsString('EXTENSION("md4c", "md4c.c", false);', $registrationPatch);
        self::assertStringContainsString('md4c config.w32 was not found.', $registrationPatch);
        self::assertStringContainsString('php_md4c.h', $registrationPatch);
        self::assertStringContainsString('phpext_md4c_ptr', $registrationPatch);
    }

    #[Test]
    public function pharBuilderExcludesVendorNonRuntimeFiles(): void
    {
        foreach ([
            'runtime/cache/build-manifest.json',
            'vendor/bin/phpunit',
            'vendor/acme/package/tests/FeatureTest.php',
            'vendor/acme/package/test/FeatureTest.php',
            'vendor/acme/package/.github/workflows/ci.yml',
            'vendor/acme/package/.phan/config.php',
            'vendor/acme/package/docs/index.md',
            'vendor/acme/package/doc/index.rst',
            'vendor/acme/package/examples/example.php',
            'vendor/acme/package/tools/.gitignore',
            'vendor/acme/package/.gitignore',
            'vendor/acme/package/.scrutinizer.yml',
            'vendor/acme/package/README.md',
            'vendor/acme/package/UPGRADE.md',
            'vendor/acme/package/composer.json',
            'vendor/acme/package/composer.lock',
            'vendor/acme/package/phpunit.xml.dist',
            'vendor/acme/package/psalm.xml',
            'vendor/acme/package/phpstan.neon.dist',
            'vendor/acme/package/rector.php',
            'vendor/acme/package/Makefile',
        ] as $path) {
            self::assertTrue(PharArchiveFilter::shouldExclude($path), $path);
        }

        foreach ([
            'config/common/params.php',
            'src/Console/BuildCommand.php',
            'themes/default/layout.php',
            'vendor/composer/autoload_real.php',
            'vendor/composer/installed.php',
            'vendor/acme/package/src/Runtime.php',
            'vendor/acme/package/LICENSE',
            'vendor/acme/package/LICENSE.md',
            'vendor/acme/package/LICENCE.md',
        ] as $path) {
            self::assertFalse(PharArchiveFilter::shouldExclude($path), $path);
        }
    }

    #[Test]
    public function dockerBuildsHighlighterExtensionFromPackagistPackage(): void
    {
        $dockerfile = file_get_contents(dirname(__DIR__, 3) . '/docker/Dockerfile');
        self::assertIsString($dockerfile);

        self::assertStringContainsString('composer create-project --no-dev --no-progress --no-interaction yiipress/highlighter', $dockerfile);
        self::assertStringContainsString('docker-php-ext-enable highlighter', $dockerfile);
        self::assertStringNotContainsString('packages/highlighter-extension', $dockerfile);
        self::assertStringNotContainsString('yiipress-highligher', $dockerfile);
        self::assertStringNotContainsString('yiipress_highlighter', $dockerfile);
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

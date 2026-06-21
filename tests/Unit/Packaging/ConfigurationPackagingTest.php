<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Packaging;

use YiiPress\Console\BuildCommand;
use YiiPress\Console\CleanCommand;
use YiiPress\Console\InitCommand;
use YiiPress\Console\ImportCommand;
use YiiPress\Console\NewCommand;
use YiiPress\Console\ServeCommand;
use YiiPress\Console\ThemeInitCommand;
use YiiPress\Build\PharArchiveFilter;
use YiiPress\Build\PhpDocStripper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chdir;
use function getcwd;
use function mkdir;
use function PHPUnit\Framework\assertSame;
use function yaml_parse;

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
            assertSame($workingDirectory, $contentPipelineConfiguration[ThemeInitCommand::class]['__construct()']['rootPath']);
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
        assertSame(ThemeInitCommand::class, $commands['theme:init']);
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
    public function staticBinaryBuildKeepsOnlyRequiredXmlExtension(): void
    {
        $root = dirname(__DIR__, 3);
        $dockerfile = file_get_contents($root . '/docker/Dockerfile');
        $macosScript = file_get_contents($root . '/build/package-macos.sh');
        $windowsScript = file_get_contents($root . '/build/package-windows.ps1');
        self::assertIsString($dockerfile);
        self::assertIsString($macosScript);
        self::assertIsString($windowsScript);

        foreach ([$dockerfile, $macosScript, $windowsScript] as $source) {
            self::assertStringContainsString('xmlwriter', $source);
            self::assertStringNotContainsString(',xml,xmlwriter', $source);
        }
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
        self::assertStringContainsString('PACKAGE_MACOS_DIST ?= dist/macos-$(PACKAGE_MACOS_ARCH)', $makefile);
        self::assertStringContainsString('package-macos:', $makefile);
        self::assertStringContainsString('build/package-macos.sh --dist-dir $(PACKAGE_MACOS_DIST) --arch $(PACKAGE_MACOS_ARCH)', $makefile);
        self::assertStringContainsString('Bash is required for package-macos.', $makefile);
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

        $start = strpos(
            $dockerfile,
            'FROM gcr.io/distroless/static-debian12:nonroot@sha256:d093aa3e30dbadd3efe1310db061a14da60299baff8450a17fe0ccc514a16639 AS distroless',
        );
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
        $macosScript = file_get_contents(dirname(__DIR__, 3) . '/build/package-macos.sh');
        self::assertIsString($dockerfile);
        self::assertIsString($script);
        self::assertIsString($macosScript);

        $pharStart = strpos($dockerfile, 'FROM scratch AS package-phar-artifacts');
        $linuxStart = strpos($dockerfile, 'FROM scratch AS package-linux-artifacts');
        $staticStart = strpos($dockerfile, 'FROM scratch AS package-static-artifacts');
        $aliasStart = strpos($dockerfile, 'FROM package-linux-artifacts AS package-artifacts');

        self::assertIsInt($pharStart);
        self::assertIsInt($linuxStart);
        self::assertIsInt($staticStart);
        self::assertIsInt($aliasStart);

        $pharStage = substr($dockerfile, $pharStart, $linuxStart - $pharStart);
        $linuxStage = substr($dockerfile, $linuxStart, $staticStart - $linuxStart);
        $staticStage = substr($dockerfile, $staticStart, $aliasStart - $staticStart);

        self::assertStringContainsString('COPY --from=phar-builder /app/dist/yiipress.phar /yiipress.phar', $pharStage);
        self::assertStringContainsString('COPY --from=static-package /artifacts/yiipress /yiipress', $linuxStage);
        self::assertStringNotContainsString('yiipress.phar', $linuxStage);
        self::assertStringContainsString('COPY --from=static-package /artifacts/yiipress /yiipress', $staticStage);
        self::assertStringContainsString('COPY --from=static-package /artifacts/yiipress.phar /yiipress.phar', $staticStage);
        self::assertStringContainsString('$pharPath = Join-Path $workPath "yiipress.phar"', $script);
        self::assertStringNotContainsString('$pharPath = Join-Path $distPath "yiipress.phar"', $script);
        self::assertStringContainsString('$legacyDistPharPath = Join-Path $distPath "yiipress.phar"', $script);
        self::assertStringContainsString('Remove-Item $legacyDistPharPath -Force', $script);
        self::assertStringContainsString('PHAR_PATH="${WORK_PATH}/yiipress.phar"', $macosScript);
        self::assertStringNotContainsString('PHAR_PATH="${DIST_PATH}/yiipress.phar"', $macosScript);
        self::assertStringContainsString('rm -f "${DIST_PATH}/yiipress.phar"', $macosScript);
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
    public function macosPackageScriptBuildsPharAndExecutable(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3) . '/build/package-macos.sh');
        self::assertIsString($script);

        self::assertStringContainsString('build/package-phar.php', $script);
        self::assertStringContainsString('BIN_PATH="${DIST_PATH}/yiipress"', $script);
        self::assertStringContainsString('HOST_ARCH="$(detect_arch)"', $script);
        self::assertStringContainsString('package-macos does not support cross-compilation', $script);
        self::assertStringContainsString('aarch64-apple-darwin', $script);
        self::assertStringContainsString('x86_64-apple-darwin', $script);
        self::assertStringContainsString('micro:combine', $script);
        self::assertStringContainsString('APP_PATH="${WORK_PATH}/app"', $script);
        self::assertStringContainsString('pushd "$APP_PATH"', $script);
        self::assertStringContainsString('require_command "$command"', $script);
        self::assertStringContainsString('for command in php composer tar curl rustup cargo make; do', $script);
        self::assertStringContainsString('curl -fsSL --max-time 300 --retry 3 --retry-delay 5 "$url" -o "$archive"', $script);
        self::assertStringContainsString('HIGHLIGHTER_VERSION="${HIGHLIGHTER_VERSION:-1.0.1}"', $script);
        self::assertStringNotContainsString('dev-master', $script);
        self::assertStringContainsString('--ignore-platform-req=ext-inotify', $script);
        self::assertStringContainsString('--ignore-platform-req=ext-md4c', $script);
        self::assertStringContainsString('--ignore-platform-req=ext-yaml', $script);
        self::assertStringContainsString('--ignore-platform-req=ext-highlighter', $script);
        self::assertStringContainsString('write_log_tail "${STATIC_PHP_PATH}/log/spc.output.log"', $script);
        self::assertStringContainsString('write_log_tail "${STATIC_PHP_PATH}/log/spc.shell.log"', $script);
        self::assertStringNotContainsString('--with-micro-fake-cli', $script);
        self::assertStringContainsString('rustup target add "$CARGO_BUILD_TARGET"', $script);
        self::assertStringContainsString('cargo build --release --target "$CARGO_BUILD_TARGET"', $script);
        self::assertStringContainsString('chmod +x "$BIN_PATH"', $script);
    }

    #[Test]
    public function packageWorkflowPublishesNightlyBuilds(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 3) . '/.github/workflows/package-static.yml');
        self::assertIsString($workflow);

        self::assertStringContainsString('target: package-static-artifacts', $workflow);
        self::assertStringContainsString('name: yiipress-phar', $workflow);
        self::assertStringContainsString('path: dist/linux-amd64/yiipress.phar', $workflow);
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
        self::assertStringContainsString('runs-on: macos-latest', $workflow);
        self::assertStringContainsString('targets: aarch64-apple-darwin', $workflow);
        self::assertStringContainsString('Cache macOS package dependencies', $workflow);
        self::assertStringContainsString('runtime/package-macos/yiipress-highlighter', $workflow);
        self::assertStringContainsString('make package-macos PACKAGE_MACOS_ARCH=arm64 PACKAGE_MACOS_DIST=dist/macos-arm64', $workflow);
        self::assertStringContainsString('Smoke test macOS binary', $workflow);
        self::assertStringContainsString('./dist/macos-arm64/yiipress --help', $workflow);
        self::assertStringContainsString('Pack macOS artifact', $workflow);
        self::assertStringContainsString('tar -C dist/macos-arm64 -czf dist/yiipress-macos-arm64.tar.gz yiipress', $workflow);
        self::assertStringContainsString('name: yiipress-macos-arm64', $workflow);
        self::assertStringContainsString('path: dist/yiipress-macos-arm64.tar.gz', $workflow);
        self::assertStringContainsString('file: ./docker/Dockerfile.distroless-binary', $workflow);
        self::assertStringContainsString("github.ref == 'refs/heads/master'", $workflow);
        self::assertStringContainsString('type=raw,value=nightly', $workflow);
        self::assertStringContainsString('type=sha,prefix=nightly-', $workflow);
        self::assertStringContainsString('nightly_tag="nightly-${GITHUB_RUN_NUMBER}-${GITHUB_RUN_ATTEMPT}-${short_sha}"', $workflow);
        self::assertStringContainsString('gh release create "${nightly_tag}" assets/*', $workflow);
        self::assertStringContainsString('--target "${GITHUB_SHA}"', $workflow);
        self::assertStringContainsString('--latest=false', $workflow);
        self::assertStringNotContainsString('git/ref/tags/nightly', $workflow);
        self::assertStringNotContainsString('refs/tags/nightly', $workflow);
        self::assertStringNotContainsString('gh release create nightly', $workflow);
        self::assertStringNotContainsString('gh release view nightly', $workflow);
        self::assertStringNotContainsString('type=semver,pattern={{version}}', $workflow);
        self::assertStringNotContainsString("startsWith(github.ref, 'refs/tags/')", $workflow);
        self::assertStringContainsString('yiipress-macos-arm64.tar.gz', $workflow);
        self::assertDoesNotMatchRegularExpression('/uses:\s+[^@\s]+@v\d+/', $workflow);
        self::assertStringNotContainsString('dtolnay/rust-toolchain@stable', $workflow);
        self::assertSame(3, substr_count($workflow, 'persist-credentials: false'));
    }

    #[Test]
    public function buildActionInitializesBinaryOutsideCheckoutByDefault(): void
    {
        $action = file_get_contents(dirname(__DIR__, 3) . '/.github/actions/build/action.yml');
        self::assertIsString($action);

        self::assertMatchesRegularExpression(
            '/binary-path:\n\s+description: [^\n]+\n\s+required: false\n\s+default: ""/',
            $action,
        );
        self::assertStringContainsString('binary_path="${RUNNER_TEMP}/yiipress"', $action);
        self::assertStringContainsString('elif [[ "${BINARY_PATH}" = /* ]]; then', $action);
        self::assertStringContainsString('printf \'binary-path=%s\n\' "${binary_path}"', $action);
    }

    #[Test]
    public function buildActionManifestIsValidYaml(): void
    {
        $action = file_get_contents(dirname(__DIR__, 3) . '/.github/actions/build/action.yml');
        self::assertIsString($action);

        $manifest = yaml_parse($action);

        self::assertIsArray($manifest);
        self::assertSame('composite', $manifest['runs']['using'] ?? null);
    }

    #[Test]
    public function buildActionResolvesNightlyToLatestImmutablePrerelease(): void
    {
        $action = file_get_contents(dirname(__DIR__, 3) . '/.github/actions/build/action.yml');
        self::assertIsString($action);

        self::assertStringContainsString('elif [ "${version}" = "nightly" ]; then', $action);
        self::assertStringContainsString('max_pages=10', $action);
        self::assertStringContainsString('while [ "${page}" -le "${max_pages}" ]; do', $action);
        self::assertStringContainsString('https://api.github.com/repos/${repository}/releases?per_page=100&page=${page}', $action);
        self::assertStringContainsString('not release.get("draft")', $action);
        self::assertStringContainsString('release.get("prerelease")', $action);
        self::assertStringContainsString('tag.startswith("nightly-")', $action);
        self::assertStringContainsString('{"yiipress-linux-amd64.tar.gz", "SHA256SUMS"}.issubset(assets)', $action);
        self::assertStringContainsString('len(json.load(open(sys.argv[1], encoding="utf-8"))) < 100', $action);
        self::assertStringContainsString('Could not find a nightly YiiPress release', $action);
    }

    #[Test]
    public function documentationWorkflowUsesNightlyBinaryAfterPackageWorkflow(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 3) . '/.github/workflows/build-docs.yml');
        self::assertIsString($workflow);

        self::assertStringContainsString('workflow_run:', $workflow);
        self::assertStringContainsString('workflows: ["Package Static Builds"]', $workflow);
        self::assertStringContainsString("github.event.workflow_run.conclusion == 'success'", $workflow);
        self::assertStringContainsString('uses: actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5', $workflow);
        self::assertStringContainsString('persist-credentials: false', $workflow);
        self::assertStringContainsString('mkdir -p _site', $workflow);
        self::assertStringContainsString('user_id="$(id -u):$(id -g)"', $workflow);
        self::assertStringContainsString('--user "${user_id}"', $workflow);
        self::assertStringContainsString('ghcr.io/yiipress/engine-static:nightly', $workflow);
        self::assertStringContainsString('build --content-dir=docs --output-dir=_site --no-cache', $workflow);
        self::assertStringNotContainsString('uses: ./.github/actions/build', $workflow);
        self::assertStringNotContainsString('--user=root', $workflow);
    }

    #[Test]
    public function releaseWorkflowBuildsNotesFromPreviousStableReleaseTag(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 3) . '/.github/workflows/release.yml');
        self::assertIsString($workflow);

        self::assertStringContainsString("tags:\n      - '*.*.*'", $workflow);
        self::assertStringContainsString(
            "git describe --tags --abbrev=0 --match '[0-9]*.[0-9]*.[0-9]*' --exclude '*[!0-9.]*' \"\${tag}^\"",
            $workflow,
        );
        self::assertStringContainsString('release_author() {', $workflow);
        self::assertStringContainsString('*+*@users.noreply.github.com)', $workflow);
        self::assertStringContainsString('sam@rmcreative.ru)', $workflow);
        self::assertStringContainsString('nickname="samdark"', $workflow);
        self::assertStringContainsString('pamparam83@gmail.com)', $workflow);
        self::assertStringContainsString('nickname="pamparam83"', $workflow);
        self::assertStringContainsString("git log --format='%s%x09%an%x09%ae'", $workflow);
        self::assertStringNotContainsString('repos/${GITHUB_REPOSITORY}/commits/${commit}', $workflow);
        self::assertStringNotContainsString("git log --format='- %s (%an)'", $workflow);
        self::assertStringNotContainsString('git describe --tags --abbrev=0 "${tag}^"', $workflow);
        self::assertStringContainsString('Changes since %s:', $workflow);
    }

    #[Test]
    public function cloudflareDeploymentVerifiesDownloadedBinaryChecksum(): void
    {
        $documentation = file_get_contents(dirname(__DIR__, 3) . '/docs/deployment.md');
        self::assertIsString($documentation);

        self::assertStringContainsString('SHA256SUMS', $documentation);
        self::assertStringContainsString('test -n "$checksum"', $documentation);
        self::assertStringContainsString('sha256sum -c -', $documentation);
        self::assertStringContainsString('yiipress-linux-amd64.tar.gz', $documentation);
        self::assertStringNotContainsString('curl -fsSL https://github.com/yiipress/engine/releases/download/X.Y.Z/yiipress-linux-amd64.tar.gz | tar -xz', $documentation);
    }

    #[Test]
    public function readmeExposesStaticAnalysisAndCoverageBadges(): void
    {
        $readme = file_get_contents(dirname(__DIR__, 3) . '/README.md');
        self::assertIsString($readme);

        self::assertStringContainsString(
            '[![Static Analysis](https://github.com/yiipress/engine/actions/workflows/static-analysis.yml/badge.svg)]',
            $readme,
        );
        self::assertStringContainsString(
            '[![Coverage](https://codecov.io/gh/yiipress/engine/branch/master/graph/badge.svg)]',
            $readme,
        );
    }

    #[Test]
    public function staticAnalysisWorkflowRunsProjectAnalysisTargets(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 3) . '/.github/workflows/static-analysis.yml');
        $psalmConfiguration = file_get_contents(dirname(__DIR__, 3) . '/psalm.xml');
        $psalmBaseline = file_get_contents(dirname(__DIR__, 3) . '/psalm-baseline.xml');
        self::assertIsString($workflow);
        self::assertIsString($psalmConfiguration);
        self::assertIsString($psalmBaseline);

        self::assertStringContainsString('name: Static Analysis', $workflow);
        self::assertStringContainsString('uses: actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5', $workflow);
        self::assertStringContainsString('persist-credentials: false', $workflow);
        self::assertStringContainsString('make -- composer install --no-progress --no-interaction', $workflow);
        self::assertStringContainsString('make psalm', $workflow);
        self::assertStringContainsString('make composer-dependency-analyser', $workflow);
        self::assertDoesNotMatchRegularExpression('/uses:\s+[^@\s]+@v\d+/', $workflow);
        self::assertStringContainsString('errorBaseline="psalm-baseline.xml"', $psalmConfiguration);
        self::assertStringContainsString('<files psalm-version=', $psalmBaseline);
    }

    #[Test]
    public function coverageWorkflowPublishesRealCloverReport(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 3) . '/.github/workflows/coverage.yml');
        $makefile = file_get_contents(dirname(__DIR__, 3) . '/Makefile');
        self::assertIsString($workflow);
        self::assertIsString($makefile);

        self::assertStringContainsString('name: Coverage', $workflow);
        self::assertStringContainsString('uses: actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5', $workflow);
        self::assertStringContainsString('persist-credentials: false', $workflow);
        self::assertStringContainsString('make -- composer install --no-progress --no-interaction', $workflow);
        self::assertStringContainsString('make test-coverage-clover', $workflow);
        self::assertStringContainsString('runtime/coverage/clover.xml', $workflow);
        self::assertStringContainsString('CODECOV_TOKEN: ${{ secrets.CODECOV_TOKEN }}', $workflow);
        self::assertStringContainsString("if: env.CODECOV_TOKEN != ''", $workflow);
        self::assertStringContainsString('token: ${{ env.CODECOV_TOKEN }}', $workflow);
        self::assertStringContainsString('uses: codecov/codecov-action@e79a6962e0d4c0c17b229090214935d2e33f8354', $workflow);
        self::assertStringContainsString('fail_ci_if_error: true', $workflow);
        self::assertStringContainsString("if: env.CODECOV_TOKEN == ''", $workflow);
        self::assertStringContainsString('generated coverage but skipped Codecov upload', $workflow);
        self::assertDoesNotMatchRegularExpression('/uses:\s+[^@\s]+@v\d+/', $workflow);
        self::assertStringContainsString('test-coverage-clover: ## Run tests with Clover coverage', $makefile);
        self::assertStringContainsString('--coverage-clover runtime/coverage/clover.xml', $makefile);
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
        self::assertStringContainsString(
            'COPY build/package-phar.php build/PharArchiveFilter.php build/PhpDocStripper.php /app/build/',
            $stage,
        );
        self::assertStringContainsString('COPY yii composer.json composer.lock /app/', $stage);
    }

    #[Test]
    public function pharBuilderStripsPhpDocFromPackagedPhpFiles(): void
    {
        $packageScript = file_get_contents(dirname(__DIR__, 3) . '/build/package-phar.php');
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsString($packageScript);
        self::assertIsArray($composer);

        self::assertStringContainsString('require_once __DIR__ . \'/PhpDocStripper.php\';', $packageScript);
        self::assertStringContainsString('str_replace(\'\\\\\', \'/\', substr($fullPath', $packageScript);
        self::assertStringContainsString('PhpDocStripper::shouldStrip($localPath)', $packageScript);
        self::assertStringContainsString('PhpDocStripper::strip($contents)', $packageScript);
        self::assertStringContainsString('$phar->addFromString($localPath', $packageScript);
        self::assertContains('build/PharArchiveFilter.php', $composer['autoload-dev']['classmap'] ?? []);
        self::assertContains('build/PhpDocStripper.php', $composer['autoload-dev']['classmap'] ?? []);
        self::assertArrayNotHasKey('classmap', $composer['autoload']);
        self::assertTrue(PhpDocStripper::shouldStrip('src/Render/MarkdownRenderer.php'));
        self::assertTrue(PhpDocStripper::shouldStrip('vendor/acme/package/src/Runtime.php'));
        self::assertTrue(PhpDocStripper::shouldStrip('vendor\\acme\\package\\src\\Runtime.php'));
        self::assertFalse(PhpDocStripper::shouldStrip('themes/minimal/entry.html'));
        self::assertFalse(PhpDocStripper::shouldStrip('vendor/cebe/markdown/Parser.php'));
        self::assertFalse(PhpDocStripper::shouldStrip('vendor\\cebe\\markdown\\Parser.php'));
        self::assertFalse(PhpDocStripper::shouldStrip('vendor/cebe/markdown/inline/LinkTrait.php'));

        $code = <<<'PHP'
<?php

/**
 * Class documentation.
 */
final class Example
{
    // Runtime comment kept.

    /**
     * Method documentation.
     */
    public function run(): void
    {
    }
}
PHP;

        $stripped = PhpDocStripper::strip($code);

        self::assertStringNotContainsString('Class documentation', $stripped);
        self::assertStringNotContainsString('Method documentation', $stripped);
        self::assertStringContainsString('// Runtime comment kept.', $stripped);
        self::assertStringContainsString('final class Example', $stripped);
        self::assertStringContainsString('public function run(): void', $stripped);
    }

    #[Test]
    public function staticPackageInitializesRustTargetForHighlighterToolchain(): void
    {
        $dockerfile = file_get_contents(dirname(__DIR__, 3) . '/docker/Dockerfile');
        $windowsScript = file_get_contents(dirname(__DIR__, 3) . '/build/package-windows.ps1');
        $macosScript = file_get_contents(dirname(__DIR__, 3) . '/build/package-macos.sh');
        self::assertIsString($dockerfile);
        self::assertIsString($windowsScript);
        self::assertIsString($macosScript);

        self::assertStringContainsString('RUN rustup target add x86_64-unknown-linux-musl', $dockerfile);
        self::assertStringContainsString(
            'RUN cd /opt/yiipress-highlighter && rustup target add x86_64-unknown-linux-musl',
            $dockerfile,
        );
        self::assertStringContainsString('Invoke-NativeCommand "rustup" @("target", "add", $env:CARGO_BUILD_TARGET)', $windowsScript);
        self::assertStringContainsString('rustup target add "$CARGO_BUILD_TARGET"', $macosScript);
    }

    #[Test]
    public function platformPackageScriptsUseStrippedPharBuilderSupportFiles(): void
    {
        $windowsScript = file_get_contents(dirname(__DIR__, 3) . '/build/package-windows.ps1');
        $macosScript = file_get_contents(dirname(__DIR__, 3) . '/build/package-macos.sh');
        self::assertIsString($windowsScript);
        self::assertIsString($macosScript);

        self::assertStringContainsString('build/PhpDocStripper.php', $macosScript);
        self::assertStringContainsString('build/PhpDocStripper.php', $windowsScript);
        self::assertStringContainsString('Invoke-NativeCommand "php" @("-d", "phar.readonly=0", "build/package-phar.php", $pharPath)', $windowsScript);
        self::assertStringContainsString('invoke php -d phar.readonly=0 build/package-phar.php "$PHAR_PATH"', $macosScript);
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
        self::assertStringContainsString(
            'str_contains($highlighterWindowsConfigContents, \'EXTENSION("highlighter", "highlighter.c", false);\')',
            $registrationPatch,
        );
        self::assertStringContainsString('EXTENSION("md4c", "md4c.c", false);', $registrationPatch);
        self::assertStringContainsString('md4c config.w32 was not found.', $registrationPatch);
        self::assertStringContainsString('php_md4c.h', $registrationPatch);
        self::assertStringContainsString('phpext_md4c_ptr', $registrationPatch);
    }

    #[Test]
    public function pharBuilderExcludesVendorNonRuntimeFiles(): void
    {
        foreach ([
            'config/.gitignore',
            'runtime/cache/build-manifest.json',
            'vendor/composer/installed.json',
            'vendor\\composer\\installed.json',
            'vendor/nikic/fast-route/FastRoute.hhi',
            'vendor\\nikic\\fast-route\\FastRoute.hhi',
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
            'vendor\\composer\\autoload_real.php',
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

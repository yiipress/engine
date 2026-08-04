<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Packaging;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function fclose;
use function getenv;
use function hash_file;
use function is_dir;
use function mkdir;
use function proc_close;
use function proc_open;
use function readdir;
use function rmdir;
use function stream_get_contents;
use function str_replace;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class InstallerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-installer-' . uniqid('', true);
        mkdir($this->root . '/bin', recursive: true);
        mkdir($this->root . '/release', recursive: true);
        mkdir($this->root . '/install', recursive: true);

        $curl = str_replace("\r\n", "\n", <<<'SH'
#!/bin/sh
set -eu
url=""
output=""
write_out=""
while [ "$#" -gt 0 ]; do
    case "$1" in
        -o) output="$2"; shift 2 ;;
        -w) write_out="$2"; shift 2 ;;
        http*) url="$1"; shift ;;
        *) shift ;;
    esac
done
case "$url" in
    https://api.github.com/*page=2) cp "${YIIPRESS_TEST_RELEASES_PAGE_2}" "$output" ;;
    https://api.github.com/*) cp "${YIIPRESS_TEST_RELEASES_PAGE_1}" "$output" ;;
    *) cp "${YIIPRESS_TEST_RELEASE_DIR}/${url##*/}" "$output" ;;
esac
printf '%s\n' "$url" >> "${YIIPRESS_TEST_CURL_LOG}"
if [ "$write_out" = '%{redirect_url}' ]; then
    printf '%s\n' "https://github.com/test/engine/releases/download/${YIIPRESS_TEST_LATEST_VERSION}/${url##*/}?sig=test&expires=1"
fi
SH);
        file_put_contents($this->root . '/bin/curl', $curl);
        chmod($this->root . '/bin/curl', 0755);

        $uname = "#!/bin/sh\nif [ \"\$1\" = \"-s\" ]; then printf '%s\\n' \"\$YIIPRESS_TEST_SYSTEM\"; else printf '%s\\n' \"\$YIIPRESS_TEST_MACHINE\"; fi\n";
        file_put_contents($this->root . '/bin/uname', $uname);
        chmod($this->root . '/bin/uname', 0755);

        $shasum = "#!/bin/sh\nshift 2\nexec sha256sum \"\$@\"\n";
        file_put_contents($this->root . '/bin/shasum', $shasum);
        chmod($this->root . '/bin/shasum', 0755);

        $sudo = "#!/bin/sh\nprintf '%s\\n' \"\$*\" >> \"\$YIIPRESS_TEST_SUDO_LOG\"\n";
        file_put_contents($this->root . '/bin/sudo', $sudo);
        chmod($this->root . '/bin/sudo', 0755);

        file_put_contents(
            $this->root . '/releases-page-1.json',
            "[{\"prerelease\":false,\"tag_name\":\"1.2.3\"}]\n",
        );
        file_put_contents(
            $this->root . '/releases-page-2.json',
            "[{\"prerelease\":true,\"draft\":false,\"tag_name\":\"nightly-42-1-abcdef123456\"}]\n",
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    #[Test]
    public function installsAndUpdatesTheLatestLinuxBinary(): void
    {
        $this->createRelease('version-one');

        [$exitCode, $output] = $this->runInstaller();

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('Downloading YiiPress 1.2.3 for linux/amd64...', $output);
        $curlLog = file_get_contents($this->root . '/curl.log');
        self::assertIsString($curlLog);
        self::assertStringContainsString('/releases/download/1.2.3/SHA256SUMS', $curlLog);
        self::assertStringContainsString('/releases/download/1.2.3/yiipress-linux-amd64.tar.gz', $curlLog);
        self::assertStringNotContainsString('?sig=test', $curlLog);
        self::assertSame('version-one', file_get_contents($this->root . '/install/yiipress'));
        self::assertSame(0755, fileperms($this->root . '/install/yiipress') & 0777);

        $this->createRelease('version-two');
        [$exitCode, $output] = $this->runInstaller();

        self::assertSame(0, $exitCode, $output);
        self::assertSame('version-two', file_get_contents($this->root . '/install/yiipress'));
        self::assertSame([], glob($this->root . '/install/.yiipress.*'));
    }

    #[Test]
    public function refusesAnArchiveWithAnInvalidChecksum(): void
    {
        $this->createRelease('untrusted');
        file_put_contents($this->root . '/release/SHA256SUMS', sprintf("%064d  yiipress-linux-amd64.tar.gz\n", 0));

        [$exitCode, $output] = $this->runInstaller();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Checksum verification failed', $output);
        self::assertFileDoesNotExist($this->root . '/install/yiipress');
    }

    #[Test]
    public function refusesASymbolicLinkBinaryFromTheArchive(): void
    {
        $this->createRelease('unused', symbolicLink: true);

        [$exitCode, $output] = $this->runInstaller();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('does not contain the yiipress binary', $output);
        self::assertFileDoesNotExist($this->root . '/install/yiipress');
    }

    #[Test]
    public function installsTheLatestMacOsArmBinary(): void
    {
        $this->createRelease('macos-version', 'yiipress-macos-arm64.tar.gz');

        [$exitCode, $output] = $this->runInstaller('Darwin', 'arm64');

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('macos/arm64', $output);
        self::assertSame('macos-version', file_get_contents($this->root . '/install/yiipress'));
    }

    #[Test]
    public function installsAPinnedReleaseIntoTheConfiguredDirectory(): void
    {
        $this->createRelease('pinned-version');
        $installDirectory = $this->root . '/custom-install';

        [$exitCode, $output] = $this->runInstaller(installDirectory: $installDirectory, version: '0.1.8');

        self::assertSame(0, $exitCode, $output);
        self::assertSame('pinned-version', file_get_contents($installDirectory . '/yiipress'));
        $curlLog = file_get_contents($this->root . '/curl.log');
        self::assertIsString($curlLog);
        self::assertStringContainsString('/releases/download/0.1.8/yiipress-linux-amd64.tar.gz', $curlLog);
        self::assertStringNotContainsString('/releases/latest/download', $curlLog);
    }

    #[Test]
    public function installsTheNewestNightlyRelease(): void
    {
        $this->createRelease('nightly-version');

        [$exitCode, $output] = $this->runInstaller(version: 'nightly');

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('Downloading YiiPress nightly-42-1-abcdef123456', $output);
        self::assertSame('nightly-version', file_get_contents($this->root . '/install/yiipress'));
        $curlLog = file_get_contents($this->root . '/curl.log');
        self::assertIsString($curlLog);
        self::assertStringContainsString('/repos/test/engine/releases?per_page=100&page=1', $curlLog);
        self::assertStringContainsString('/repos/test/engine/releases?per_page=100&page=2', $curlLog);
        self::assertStringContainsString(
            '/releases/download/nightly-42-1-abcdef123456/yiipress-linux-amd64.tar.gz',
            $curlLog,
        );
        self::assertStringContainsString(
            '/releases/download/nightly-42-1-abcdef123456/SHA256SUMS',
            $curlLog,
        );
    }

    #[Test]
    public function reportsWhenANightlyReleaseIsUnavailable(): void
    {
        file_put_contents($this->root . '/releases-page-1.json', "[]\n");

        [$exitCode, $output] = $this->runInstaller(version: 'nightly');

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Could not find a YiiPress nightly release for test/engine.', $output);
        self::assertFileDoesNotExist($this->root . '/install/yiipress');
    }

    #[Test]
    public function usesSudoWhenTheInstallDirectoryCannotBeCreated(): void
    {
        $this->createRelease('sudo-version');

        [$exitCode, $output] = $this->runInstaller(installDirectory: '/proc/yiipress-installer-test');

        self::assertSame(0, $exitCode, $output);
        $sudoLog = file_get_contents($this->root . '/sudo.log');
        self::assertIsString($sudoLog);
        self::assertStringContainsString('install -d /proc/yiipress-installer-test', $sudoLog);
        self::assertStringContainsString('install -m 0755', $sudoLog);
        self::assertStringContainsString('mv -f /proc/yiipress-installer-test/.yiipress.', $sudoLog);
    }

    #[Test]
    public function windowsInstallerDownloadsVerifiesAndAtomicallyReplacesTheExecutable(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3) . '/install.ps1');
        self::assertIsString($script);

        self::assertStringContainsString('yiipress-windows-amd64.zip', $script);
        self::assertStringContainsString('OSPlatform]::Windows', $script);
        self::assertStringContainsString('releases/latest/download', $script);
        self::assertStringContainsString('Get-FileHash -Algorithm SHA256', $script);
        self::assertStringContainsString('[IO.File]::Replace($TemporaryTarget, $Target, $null)', $script);
        self::assertStringContainsString('[IO.File]::Move($TemporaryTarget, $Target)', $script);
        self::assertStringNotContainsString('[IO.File]::Move($TemporaryTarget, $Target, $true)', $script);
        self::assertStringContainsString('[Environment]::SetEnvironmentVariable("Path", $UpdatedPath, "User")', $script);
        self::assertStringContainsString('YIIPRESS_INSTALL_DIR', $script);
        self::assertStringContainsString('YIIPRESS_VERSION', $script);
    }

    private function createRelease(
        string $contents,
        string $asset = 'yiipress-linux-amd64.tar.gz',
        bool $symbolicLink = false,
    ): void {
        $archiveRoot = $this->root . '/archive';
        if (!is_dir($archiveRoot)) {
            mkdir($archiveRoot);
        }
        if ($symbolicLink) {
            symlink('/etc/passwd', $archiveRoot . '/yiipress');
        } else {
            file_put_contents($archiveRoot . '/yiipress', $contents);
        }

        $archive = $this->root . '/release/' . $asset;
        $pipes = [];
        $process = proc_open(
            ['tar', '-C', $archiveRoot, '-czf', $archive, 'yiipress'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $output);

        $checksum = hash_file('sha256', $archive);
        self::assertIsString($checksum);
        file_put_contents($this->root . '/release/SHA256SUMS', "$checksum  assets/$asset\n");
    }

    /** @return array{int, string} */
    private function runInstaller(
        string $system = 'Linux',
        string $machine = 'x86_64',
        ?string $installDirectory = null,
        string $version = 'latest',
    ): array {
        $systemPath = getenv('PATH');
        self::assertIsString($systemPath);
        $environment = [
            'PATH' => $this->root . '/bin:' . $systemPath,
            'YIIPRESS_INSTALL_DIR' => $installDirectory ?? $this->root . '/install',
            'YIIPRESS_REPOSITORY' => 'test/engine',
            'YIIPRESS_TEST_RELEASE_DIR' => $this->root . '/release',
            'YIIPRESS_TEST_RELEASES_PAGE_1' => $this->root . '/releases-page-1.json',
            'YIIPRESS_TEST_RELEASES_PAGE_2' => $this->root . '/releases-page-2.json',
            'YIIPRESS_TEST_CURL_LOG' => $this->root . '/curl.log',
            'YIIPRESS_TEST_SYSTEM' => $system,
            'YIIPRESS_TEST_MACHINE' => $machine,
            'YIIPRESS_TEST_LATEST_VERSION' => '1.2.3',
            'YIIPRESS_TEST_SUDO_LOG' => $this->root . '/sudo.log',
            'YIIPRESS_VERSION' => $version,
        ];
        $pipes = [];
        $process = proc_open(
            ['sh', dirname(__DIR__, 3) . '/install.sh'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $environment,
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $handle = opendir($directory);
        self::assertIsResource($handle);
        while (($name = readdir($handle)) !== false) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $directory . '/' . $name;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        closedir($handle);
        rmdir($directory);
    }
}

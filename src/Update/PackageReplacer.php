<?php

declare(strict_types=1);

namespace YiiPress\Update;

use Closure;
use RuntimeException;

use function fclose;
use function file_put_contents;
use function fwrite;
use function proc_close;
use function proc_open;
use function register_shutdown_function;
use function rename;
use function str_contains;
use function unlink;

final readonly class PackageReplacer implements PackageReplacerInterface
{
    /** @var Closure(Closure(): void): void */
    private Closure $shutdownRegistrar;

    /** @var Closure(string): void */
    private Closure $failureHandler;

    /**
     * @param Closure(Closure(): void): void|null $shutdownRegistrar
     * @param Closure(string): void|null $failureHandler
     */
    public function __construct(
        private string $osFamily = PHP_OS_FAMILY,
        ?Closure $shutdownRegistrar = null,
        ?Closure $failureHandler = null,
    ) {
        $this->shutdownRegistrar = $shutdownRegistrar ?? static function (Closure $callback): void {
            register_shutdown_function($callback);
        };
        $this->failureHandler = $failureHandler ?? static function (string $message): never {
            fwrite(STDERR, $message . "\n");
            exit(1);
        };
    }

    public function replace(string $temporaryPath, string $targetPath): void
    {
        if ($this->osFamily !== 'Windows') {
            ($this->shutdownRegistrar)(function () use ($temporaryPath, $targetPath): void {
                if (!@rename($temporaryPath, $targetPath)) {
                    ($this->failureHandler)("Could not replace $targetPath. Check its permissions.");
                }
            });
            return;
        }

        $this->scheduleWindowsReplacement($temporaryPath, $targetPath);
    }

    private function scheduleWindowsReplacement(string $temporaryPath, string $targetPath): void
    {
        if (str_contains($temporaryPath, '%') || str_contains($targetPath, '%')) {
            throw new RuntimeException('Self-update does not support Windows installation paths containing %.');
        }

        $scriptPath = $temporaryPath . '.cmd';
        $script = "@echo off\r\n"
            . ":retry\r\n"
            . "move /Y \"$temporaryPath\" \"$targetPath\" >nul 2>&1\r\n"
            . "if errorlevel 1 (\r\n"
            . "  timeout /t 1 /nobreak >nul\r\n"
            . "  goto retry\r\n"
            . ")\r\n"
            . "del \"%~f0\"\r\n";
        if (file_put_contents($scriptPath, $script, LOCK_EX) === false) {
            throw new RuntimeException('Could not create the Windows update helper.');
        }

        $pipes = [];
        $process = @proc_open(
            ['cmd.exe', '/d', '/c', 'start', '', '/b', $scriptPath],
            [0 => ['pipe', 'r'], 1 => ['file', 'NUL', 'a'], 2 => ['file', 'NUL', 'a']],
            $pipes,
        );
        if (!is_resource($process)) {
            @unlink($scriptPath);
            throw new RuntimeException('Could not start the Windows update helper.');
        }
        fclose($pipes[0]);
        if (proc_close($process) !== 0) {
            @unlink($scriptPath);
            throw new RuntimeException('Could not start the Windows update helper.');
        }
    }
}

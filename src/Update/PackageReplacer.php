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

    /** @var Closure(list<string>): bool */
    private Closure $processStarter;

    /**
     * @param Closure(Closure(): void): void|null $shutdownRegistrar
     * @param Closure(string): void|null $failureHandler
     * @param Closure(list<string>): bool|null $processStarter
     */
    public function __construct(
        private string $osFamily = PHP_OS_FAMILY,
        ?Closure $shutdownRegistrar = null,
        ?Closure $failureHandler = null,
        ?Closure $processStarter = null,
    ) {
        $this->shutdownRegistrar = $shutdownRegistrar ?? static function (Closure $callback): void {
            register_shutdown_function($callback);
        };
        $this->failureHandler = $failureHandler ?? static function (string $message): never {
            fwrite(STDERR, $message . "\n");
            exit(1);
        };
        $this->processStarter = $processStarter ?? self::startProcess(...);
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

        if (!(($this->processStarter)([
            'cmd.exe',
            '/d',
            '/c',
            'start',
            '',
            '/b',
            'cmd.exe',
            '/d',
            '/c',
            'call',
            $scriptPath,
        ]))) {
            @unlink($scriptPath);
            throw new RuntimeException('Could not start the Windows update helper.');
        }
    }

    /** @param list<string> $command */
    private static function startProcess(array $command): bool
    {
        $pipes = [];
        $process = @proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['file', 'NUL', 'a'], 2 => ['file', 'NUL', 'a']],
            $pipes,
            options: ['bypass_shell' => true],
        );
        if (!is_resource($process)) {
            return false;
        }
        fclose($pipes[0]);

        return proc_close($process) === 0;
    }
}

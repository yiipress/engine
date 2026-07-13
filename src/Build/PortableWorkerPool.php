<?php

declare(strict_types=1);

namespace YiiPress\Build;

use RuntimeException;

use function basename;
use function bin2hex;
use function compact;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function is_dir;
use function is_file;
use function is_resource;
use function mkdir;
use function preg_match;
use function proc_close;
use function proc_open;
use function random_bytes;
use function rmdir;
use function serialize;
use function sprintf;
use function stream_get_contents;
use function proc_get_status;
use function proc_terminate;
use function time;
use function usleep;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function sys_get_temp_dir;
use function trim;
use function unlink;

final class PortableWorkerPool
{
    /** @param list<string>|null $executableCommand */
    public function __construct(private readonly ?array $executableCommand = null) {}

    /**
     * @param list<WorkerJobInterface> $jobs
     */
    public function run(array $jobs): int
    {
        if ($jobs === []) {
            return 0;
        }

        $tempDir = sys_get_temp_dir() . '/yiipress_worker_pool_' . bin2hex(random_bytes(8));
        if (!mkdir($tempDir, 0o700, true) && !is_dir($tempDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created.', $tempDir));
        }

        $workers = [];
        $files = [];
        try {
            foreach ($jobs as $index => $job) {
                $jobFile = $tempDir . '/' . $index . '.job';
                $resultFile = $tempDir . '/' . $index . '.result';
                $files[] = $jobFile;
                $files[] = $resultFile;
                if (file_put_contents($jobFile, serialize($job), LOCK_EX) === false) {
                    throw new RuntimeException(sprintf('Unable to write worker job "%s".', $jobFile));
                }

                $command = [...$this->executableCommand(), '_worker', $jobFile, $resultFile];
                $pipes = [];
                $process = proc_open($command, [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ], $pipes, getcwd() ?: null);
                if (!is_resource($process)) {
                    throw new RuntimeException('Unable to start worker process.');
                }
                fclose($pipes[0]);
                $workers[] = compact('process', 'pipes', 'jobFile', 'resultFile');
            }

            $count = 0;
            $failure = null;
            foreach ($workers as $worker) {
                $startedAt = time();
                while (true) {
                    $status = proc_get_status($worker['process']);
                    if (!($status['running'] ?? false)) {
                        break;
                    }
                    if (time() - $startedAt >= 300) {
                        proc_terminate($worker['process']);
                        $failure ??= new RuntimeException('Worker process timed out after 300 seconds.');
                        break;
                    }
                    usleep(100_000);
                }
                $stdout = stream_get_contents($worker['pipes'][1]);
                if ($stdout === false) {
                    $stdout = '';
                }
                $stderr = stream_get_contents($worker['pipes'][2]);
                if ($stderr === false) {
                    $stderr = '';
                }
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                $exitCode = proc_close($worker['process']);
                if ($exitCode !== 0 || !is_file($worker['resultFile'])) {
                    $message = $stderr !== '' ? $stderr : $stdout;
                    $failure ??= new RuntimeException('Worker process failed' . ($message !== '' ? ': ' . trim($message) : '.'));
                    continue;
                }
                $result = file_get_contents($worker['resultFile']);
                if ($result === false) {
                    throw new RuntimeException(sprintf('Unable to read worker result file "%s".', $worker['resultFile']));
                }
                $count += (int) $result;
            }

            if ($failure !== null) {
                throw $failure;
            }

            return $count;
        } finally {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    }

    /** @return list<string> */
    private function executableCommand(): array
    {
        if ($this->executableCommand !== null) {
            return $this->executableCommand;
        }

        $script = $_SERVER['argv'][0] ?? '';
        if ($script !== '' && !str_starts_with($script, '/') && !preg_match('~^[A-Za-z]:[\\\\/]~', $script)) {
            $script = (getcwd() ?: '.') . DIRECTORY_SEPARATOR . $script;
        }

        if ($script !== '' && (str_ends_with(strtolower($script), '.phar') || basename($script) === 'yii')) {
            return [PHP_BINARY, $script];
        }

        return [$script !== '' ? $script : PHP_BINARY];
    }
}

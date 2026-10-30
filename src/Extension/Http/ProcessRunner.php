<?php

declare(strict_types=1);

namespace YiiPress\Extension\Http;

use function fclose;
use function is_resource;
use function proc_close;
use function proc_open;
use function stream_get_contents;

/**
 * Runs a program without a shell and returns its exit code and output.
 *
 * @internal
 */
final class ProcessRunner
{
    /**
     * @param list<string> $command
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public function run(array $command): array
    {
        $pipes = [];
        $process = @proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            options: ['bypass_shell' => true],
        );
        if (!is_resource($process)) {
            return ['exitCode' => 127, 'stdout' => '', 'stderr' => 'Could not start ' . $command[0] . '.'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'exitCode' => proc_close($process),
            'stdout' => $stdout === false ? '' : $stdout,
            'stderr' => $stderr === false ? '' : $stderr,
        ];
    }
}

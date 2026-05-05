<?php

declare(strict_types=1);

namespace YiiPress\Web\DevServer;

use function array_is_list;
use function fclose;
use function file_get_contents;
use function is_array;
use function is_resource;
use function is_scalar;
use function is_string;
use function proc_open;
use function str_contains;
use function str_getcsv;
use function str_replace;
use function trim;
use function yaml_parse;

final readonly class EditorLauncher implements EditorLauncherInterface
{
    /**
     * @param string|array<array-key, mixed>|null $configuredEditor
     */
    public function open(string $filePath, string|array|null $configuredEditor): bool
    {
        $command = $this->command($filePath, $configuredEditor);
        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $descriptors = [
            0 => ['file', $nullDevice, 'r'],
            1 => ['file', $nullDevice, 'w'],
            2 => ['file', $nullDevice, 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return false;
        }

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        return true;
    }

    /**
     * @param string|array<array-key, mixed>|null $configuredEditor
     * @return list<string>
     */
    public function command(string $filePath, string|array|null $configuredEditor): array
    {
        if (is_string($configuredEditor) && trim($configuredEditor) !== '') {
            return $this->configuredCommand($filePath, $this->splitCommandString($configuredEditor));
        }

        if (is_array($configuredEditor) && array_is_list($configuredEditor) && $configuredEditor !== []) {
            $command = [];
            foreach ($configuredEditor as $part) {
                if (is_scalar($part)) {
                    $command[] = (string) $part;
                }
            }

            if ($command !== []) {
                return $this->configuredCommand($filePath, $command);
            }
        }

        return match (PHP_OS_FAMILY) {
            'Windows' => ['cmd', '/c', 'start', '', $filePath],
            'Darwin' => ['open', $filePath],
            default => ['xdg-open', $filePath],
        };
    }

    /**
     * @return string|list<string>|null
     */
    public function configuredEditorFromFile(string $configPath): string|array|null
    {
        $content = file_get_contents($configPath);
        if ($content === false) {
            return null;
        }

        $data = yaml_parse($content);
        if (!is_array($data)) {
            return null;
        }

        $editor = $data['editor'] ?? null;
        if (is_string($editor)) {
            $editor = trim($editor);

            return $editor === '' ? null : $editor;
        }

        if (!is_array($editor) || !array_is_list($editor)) {
            return null;
        }

        $command = [];
        foreach ($editor as $part) {
            if (is_scalar($part)) {
                $command[] = (string) $part;
            }
        }

        return $command === [] ? null : $command;
    }

    /**
     * @return list<string>
     */
    private function splitCommandString(string $command): array
    {
        $parts = str_getcsv(trim($command), ' ', '"', '\\');
        $result = [];

        foreach ($parts as $part) {
            if ($part !== null && $part !== '') {
                $result[] = $part;
            }
        }

        return $result;
    }

    /**
     * @param list<string> $command
     * @return list<string>
     */
    private function configuredCommand(string $filePath, array $command): array
    {
        $hasPlaceholder = false;
        foreach ($command as $index => $part) {
            if (str_contains($part, '{file}')) {
                $command[$index] = str_replace('{file}', $filePath, $part);
                $hasPlaceholder = true;
            }
        }

        if (!$hasPlaceholder) {
            $command[] = $filePath;
        }

        return $command;
    }
}

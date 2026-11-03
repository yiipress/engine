<?php

declare(strict_types=1);

// Explicit allowlist: never collect phpinfo(), environment dumps or provider credentials.
/**
 * @return array{
 *     php: string,
 *     binary_sha256: string|false,
 *     pcre: string,
 *     extensions: list<string>,
 *     settings: array<string, string|false>,
 *     ini_files: array<array{name: string, sha256: string|false}>,
 *     xdebug_version: string|false,
 * }
 */
function diagnosticRuntime(): array
{
    $settings = [];
    foreach ([
        'opcache.enable', 'opcache.enable_cli', 'opcache.jit', 'opcache.jit_buffer_size',
        'opcache.save_comments', 'pcre.jit', 'pcre.backtrack_limit', 'pcre.recursion_limit',
        'xdebug.mode', 'xdebug.start_with_request', 'xdebug.output_dir',
        'xdebug.profiler_output_name', 'xdebug.use_compression',
    ] as $name) {
        $settings[$name] = ini_get($name);
    }
    $extensions = get_loaded_extensions();
    sort($extensions);
    return [
        'php' => PHP_VERSION,
        'binary_sha256' => hash_file('sha256', PHP_BINARY),
        'pcre' => PCRE_VERSION,
        'extensions' => $extensions,
        'settings' => $settings,
        'ini_files' => array_map(
            static fn(string $path): array => ['name' => basename($path), 'sha256' => hash_file('sha256', $path)],
            array_filter(array_map('trim', explode(',', php_ini_scanned_files() ?: ''))),
        ),
        'xdebug_version' => phpversion('xdebug'),
    ];
}

function diagnosticProfile(): string
{
    $profile = function_exists('xdebug_get_profiler_filename') ? xdebug_get_profiler_filename() : false;
    if (!is_string($profile) || !is_file($profile)) {
        throw new RuntimeException('The requested profiler did not activate.');
    }
    return $profile;
}

$script = $_SERVER['SCRIPT_FILENAME'] ?? null;
if (is_string($script) && realpath($script) === __FILE__) {
    echo json_encode(diagnosticRuntime(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
}

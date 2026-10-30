<?php

declare(strict_types=1);

/**
 * Exits with 1 when any extension of the comma-separated list in the first argument is not loaded.
 *
 * The packaging scripts combine this file with the freshly built micro SAPI and run it, so a required
 * extension that static-php-cli left out fails the build instead of a command at run time.
 */

$required = array_filter(array_map('trim', explode(',', $argv[1] ?? '')), static fn(string $name): bool => $name !== '');
if ($required === []) {
    fwrite(STDERR, "Pass a comma-separated list of extensions to check.\n");
    exit(2);
}

$missing = array_values(array_filter($required, static fn(string $name): bool => !extension_loaded($name)));
if ($missing !== []) {
    fwrite(STDERR, 'The static binary lacks required extensions: ' . implode(', ', $missing) . ".\n");
    exit(1);
}

echo 'The static binary loads ' . implode(', ', $required) . ".\n";

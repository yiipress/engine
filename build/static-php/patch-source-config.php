<?php

declare(strict_types=1);

$path = $argv[1] ?? null;
if ($path === null) {
    fwrite(STDERR, "Usage: php patch-source-config.php <source.json>\n");
    exit(1);
}

$sources = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

$pinnedSources = [
    'curl' => [
        'url' => 'https://curl.se/download/curl-8.11.1.tar.xz',
        'filename' => 'curl-8.11.1.tar.xz',
    ],
    'frankenphp' => [
        'url' => 'https://github.com/php/frankenphp/archive/refs/tags/v1.12.2.tar.gz',
        'filename' => 'frankenphp-v1.12.2.tar.gz',
    ],
    'icu' => [
        'url' => 'https://github.com/unicode-org/icu/releases/download/release-77-1/icu4c-77_1-src.tgz',
        'filename' => 'icu4c-77_1-src.tgz',
    ],
    'libyaml' => [
        'url' => 'https://pyyaml.org/download/libyaml/yaml-0.2.5.tar.gz',
        'filename' => 'yaml-0.2.5.tar.gz',
    ],
    'openssl' => [
        'url' => 'https://github.com/openssl/openssl/releases/download/openssl-3.5.4/openssl-3.5.4.tar.gz',
        'filename' => 'openssl-3.5.4.tar.gz',
    ],
    'zlib' => [
        'url' => 'https://github.com/madler/zlib/releases/download/v1.3.1/zlib-1.3.1.tar.gz',
        'filename' => 'zlib-1.3.1.tar.gz',
    ],
];

foreach ($pinnedSources as $name => $source) {
    if (!isset($sources[$name])) {
        throw new RuntimeException("Source {$name} is not defined.");
    }

    $sources[$name] = array_diff_key(
        $sources[$name],
        array_flip(['repo', 'match', 'prefer-stable', 'alt']),
    );
    $sources[$name]['type'] = 'url';
    $sources[$name]['url'] = $source['url'];
    $sources[$name]['filename'] = $source['filename'];
}

file_put_contents(
    $path,
    json_encode($sources, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
);

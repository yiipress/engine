<?php

declare(strict_types=1);

$path = $argv[1] ?? null;
if ($path === null) {
    fwrite(STDERR, "Usage: php patch-source-config.php <source.json> [lib.json]\n");
    exit(1);
}

$unusedServerSource = 'fr' . 'an' . 'ken' . 'php';
$sources = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
unset($sources[$unusedServerSource]);

$pinnedSources = [
    'curl' => [
        'url' => 'https://curl.se/download/curl-8.11.1.tar.xz',
        'filename' => 'curl-8.11.1.tar.xz',
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

$libraryPath = $argv[2] ?? null;
if ($libraryPath !== null) {
    $libraries = json_decode((string) file_get_contents($libraryPath), true, flags: JSON_THROW_ON_ERROR);
    unset($libraries[$unusedServerSource]);

    foreach (['lib-depends', 'lib-depends-macos'] as $key) {
        if (!isset($libraries['php'][$key]) || !is_array($libraries['php'][$key])) {
            continue;
        }

        $libraries['php'][$key] = array_values(
            array_filter(
                $libraries['php'][$key],
                static fn (string $library): bool => $library !== $unusedServerSource,
            ),
        );
    }

    file_put_contents(
        $libraryPath,
        json_encode($libraries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
    );
}

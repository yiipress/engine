<?php

declare(strict_types=1);

$configPath = $argv[1] ?? null;
if ($configPath === null) {
    fwrite(STDERR, "Usage: php patch-extension-config.php <ext.json>\n");
    exit(1);
}

$json = json_decode(file_get_contents($configPath) ?: '', true, flags: JSON_THROW_ON_ERROR);
$externalExtensions = [
    'highlighter',
    'md4c',
];

foreach ($externalExtensions as $extension) {
    $json[$extension] = [
        'type' => 'builtin',
        'arg-type' => 'enable',
    ];
}
ksort($json);
file_put_contents($configPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

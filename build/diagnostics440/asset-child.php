<?php

declare(strict_types=1);

use YiiPress\Benchmarks\AssetUrlRewriterBench;

require __DIR__ . '/runtime.php';
require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/benchmarks/AssetUrlRewriterBench.php';

function diagnosticAssetCold(AssetUrlRewriterBench $benchmark): void
{
    $benchmark->benchAlreadyFingerprintedPage();
}

function diagnosticAssetSteady(AssetUrlRewriterBench $benchmark): void
{
    for ($i = 0; $i < 100; $i++) {
        $benchmark->benchAlreadyFingerprintedPage();
    }
}

$profile = diagnosticProfile();
$benchmark = new AssetUrlRewriterBench();
$benchmark->setUp(['assets' => 12]);
diagnosticAssetCold($benchmark);
diagnosticAssetSteady($benchmark);
echo json_encode([
    'runtime' => diagnosticRuntime(),
    'profile' => $profile,
    'benchmark_sha256' => hash_file('sha256', dirname(__DIR__, 2) . '/benchmarks/AssetUrlRewriterBench.php'),
    'calls' => ['cold' => 1, 'steady' => 100],
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";

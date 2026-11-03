<?php

declare(strict_types=1);

use YiiPress\Benchmarks\FeedBatchBench;

require __DIR__ . '/runtime.php';
require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/benchmarks/FeedBatchBench.php';

function diagnosticFeedTarget(FeedBatchBench $benchmark): void
{
    $benchmark->benchParallel(['limit' => 0]);
}

$profile = diagnosticProfile();
// The original generated fixture has three collections; retain actual child coverage evidence.
$collections = glob(dirname(__DIR__, 2) . '/benchmarks/data/realistic-content/collection-*', GLOB_ONLYDIR);
if ($collections === false || count($collections) !== 3) {
    throw new RuntimeException('The original three-collection Feed fixture is required.');
}
$benchmark = new FeedBatchBench();
$benchmark->setUp();
diagnosticFeedTarget($benchmark);
echo json_encode([
    'runtime' => diagnosticRuntime(),
    'profile' => $profile,
    'expected_workers' => min(4, count($collections)),
    'benchmark_sha256' => hash_file('sha256', dirname(__DIR__, 2) . '/benchmarks/FeedBatchBench.php'),
    'calls' => ['parallel_unlimited' => 1],
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";

<?php

declare(strict_types=1);

use App\Benchmark\BenchmarkDataGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';

$targetDir = $argv[1] ?? __DIR__ . '/data/realistic-content';
$entryCount = (int) ($argv[2] ?? 1_000);

echo "Generating $entryCount realistic entries in $targetDir...\n";

BenchmarkDataGenerator::generateRealisticDataset($targetDir, $entryCount);

echo "Generated $entryCount realistic entries across 3 collections.\n";

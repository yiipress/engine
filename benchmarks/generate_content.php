<?php

declare(strict_types=1);

use App\Benchmark\BenchmarkDataGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';

$targetDir = $argv[1] ?? __DIR__ . '/data/content';
$entryCount = (int) ($argv[2] ?? 10_000);

echo "Generating $entryCount entries in $targetDir...\n";

BenchmarkDataGenerator::generateSmallDataset($targetDir, $entryCount);

echo "Generated $entryCount entries across 3 collections.\n";

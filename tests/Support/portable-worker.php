<?php

declare(strict_types=1);

use YiiPress\Build\ExecutableWorkerJobInterface;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function unserialize;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$contents = file_get_contents($argv[2]);
$job = $contents === false ? false : unserialize($contents, ['allowed_classes' => true]);
if (!$job instanceof ExecutableWorkerJobInterface) {
    exit(1);
}

file_put_contents($argv[3], (string) $job->run(), LOCK_EX);

<?php

declare(strict_types=1);

use App\Console;

return [
    'build' => Console\BuildCommand::class,
    'clean' => Console\CleanCommand::class,
    'import' => Console\ImportCommand::class,
    'new' => Console\NewCommand::class,
];

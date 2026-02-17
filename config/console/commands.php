<?php

declare(strict_types=1);

use App\Console;

return [
    'hello' => Console\HelloCommand::class,
    'build' => Console\BuildCommand::class,
    'clean' => Console\CleanCommand::class,
];

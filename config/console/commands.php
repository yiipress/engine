<?php

declare(strict_types=1);

use YiiPress\Console;

return [
    'build' => Console\BuildCommand::class,
    'clean|clear' => Console\CleanCommand::class,
    'import' => Console\ImportCommand::class,
    'new' => Console\NewCommand::class,
    'serve' => Console\ServeCommand::class,
];

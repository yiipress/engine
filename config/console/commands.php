<?php

declare(strict_types=1);

use YiiPress\Console;

return [
    '|worker' => Console\WorkerCommand::class,
    'build' => Console\BuildCommand::class,
    'check:links' => Console\CheckCommand::class,
    'clean|clear' => Console\CleanCommand::class,
    'init' => Console\InitCommand::class,
    'import' => Console\ImportCommand::class,
    'new' => Console\NewCommand::class,
    'serve' => Console\ServeCommand::class,
    'self-update' => Console\SelfUpdateCommand::class,
    'theme:init' => Console\ThemeInitCommand::class,
];

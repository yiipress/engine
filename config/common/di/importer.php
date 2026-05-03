<?php

declare(strict_types=1);

use App\Console\ImportCommand;
use App\Import\Telegram\TelegramContentImporter;

$workingDirectory = getcwd() ?: dirname(__DIR__, 3);

return [
    ImportCommand::class => [
        '__construct()' => [
            'rootPath' => $workingDirectory,
            'importers' => [
                'telegram' => new TelegramContentImporter(),
            ],
        ],
    ],
];

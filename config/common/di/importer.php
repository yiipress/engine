<?php

declare(strict_types=1);

use YiiPress\Console\ImportCommand;
use YiiPress\Import\Ghost\GhostContentImporter;
use YiiPress\Import\Telegram\TelegramContentImporter;

$workingDirectory = getcwd() ?: dirname(__DIR__, 3);

return [
    ImportCommand::class => [
        '__construct()' => [
            'rootPath' => $workingDirectory,
            'importers' => [
                'ghost' => new GhostContentImporter(),
                'telegram' => new TelegramContentImporter(),
            ],
        ],
    ],
];

<?php

declare(strict_types=1);

use App\Console\ImportCommand;
use App\Import\Telegram\TelegramContentImporter;

return [
    ImportCommand::class => [
        '__construct()' => [
            'rootPath' => dirname(__DIR__, 3),
            'importers' => [
                'telegram' => new TelegramContentImporter(),
            ],
        ],
    ],
];

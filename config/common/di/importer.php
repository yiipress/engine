<?php

declare(strict_types=1);

use YiiPress\Console\ImportCommand;
use YiiPress\Import\Hugo\HugoContentImporter;
use YiiPress\Import\Telegram\TelegramContentImporter;

$workingDirectory = getcwd() ?: dirname(__DIR__, 3);

return [
    ImportCommand::class => [
        '__construct()' => [
            'rootPath' => $workingDirectory,
            'importers' => [
                'hugo' => new HugoContentImporter(),
                'telegram' => new TelegramContentImporter(),
            ],
        ],
    ],
];

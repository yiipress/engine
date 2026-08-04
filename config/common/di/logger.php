<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Yiisoft\Definitions\ReferencesArray;
use Yiisoft\Log\Logger;
use Yiisoft\Log\StreamTarget;

return [
    StreamTarget::class => [
        'class' => StreamTarget::class,
        '__construct()' => [
            'stream' => 'php://stderr',
        ],
        'setEnabled()' => [
            static fn(): bool => PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg',
        ],
    ],
    LoggerInterface::class => [
        'class' => Logger::class,
        '__construct()' => [
            'targets' => ReferencesArray::from([
                StreamTarget::class,
            ]),
        ],
    ],
];

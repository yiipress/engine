<?php

declare(strict_types=1);

use YiiPress\ApplicationInfo;

return [
    'yiisoft/yii-console' => [
        'name' => ApplicationInfo::NAME,
        'version' => ApplicationInfo::version(),
        'commands' => require __DIR__ . '/commands.php',
    ],
];

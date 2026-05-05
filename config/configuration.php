<?php

declare(strict_types=1);

use YiiPress\Environment;

// NOTE: After making changes in this file, run `composer yii-config-rebuild` to update the merge plan.
return [
    'config-plugin' => [
        'params' => 'common/params.php',
        'params-web' => [
            '$params',
            'web/params.php',
        ],
        'params-console' => [
            '$params',
            'console/params.php',
        ],
        'di' => [
            'common/di/content-pipeline.php',
            'common/di/error-handler.php',
            'common/di/importer.php',
            'common/di/dev-server.php',
            'common/di/logger.php',
            'common/di/router.php',
            'common/di/theme.php',
        ],
        'di-web' => [
            '$di',
            'web/di/application.php',
            'web/di/psr17.php',
        ],
        'di-console' => '$di',
        'di-delegates' => [],
        'di-delegates-console' => '$di-delegates',
        'di-delegates-web' => '$di-delegates',
        'di-providers' => [],
        'di-providers-web' => [
            '$di-providers',
        ],
        'di-providers-console' => [
            '$di-providers',
        ],
        'events' => [],
        'events-web' => ['$events'],
        'events-console' => ['$events'],
        'routes' => 'common/routes.php',
        'bootstrap' => 'common/bootstrap.php',
        'bootstrap-web' => '$bootstrap',
        'bootstrap-console' => '$bootstrap',
    ],
    'config-plugin-environments' => [
        Environment::DEV => [
            'params' => [
                'environments/dev/params.php',
            ],
        ],
        Environment::TEST => [
            'params' => [
                'environments/test/params.php',
            ],
        ],
        Environment::PROD => [
            'params' => [
                'environments/prod/params.php',
            ],
        ],
    ],
    'config-plugin-options' => [
        'source-directory' => 'config',
    ],
];

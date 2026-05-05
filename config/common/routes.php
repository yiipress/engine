<?php

declare(strict_types=1);

use YiiPress\Web\DevServer\OpenSourceAction;
use YiiPress\Web\StaticFile\StaticFileAction;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create()
        ->routes(
            Route::post('/_open-source')
                ->action(OpenSourceAction::class)
                ->name('open-source'),
            Route::get('/{path:.*}')
                ->action(StaticFileAction::class)
                ->name('static-file'),
        ),
];

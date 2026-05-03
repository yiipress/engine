<?php

declare(strict_types=1);

use YiiPress\Web\StaticFile\StaticFileAction;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create()
        ->routes(
            Route::get('/{path:.*}')
                ->action(StaticFileAction::class)
                ->name('static-file'),
        ),
];

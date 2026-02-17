<?php

declare(strict_types=1);

use App\Web;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create()
        ->routes(
            Route::get('/_live-reload')
                ->action(Web\LiveReload\LiveReloadAction::class)
                ->name('live-reload'),
            Route::get('/{path:.*}')
                ->action(Web\StaticFile\StaticFileAction::class)
                ->name('static-file'),
        ),
];

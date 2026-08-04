<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Throwable;
use Yiisoft\Yii\Console\ExitCode;

use function is_int;

final class ExceptionExitCode
{
    public function resolve(Throwable $throwable): int
    {
        $code = $throwable->getCode();

        return is_int($code) && $code > ExitCode::OK ? $code : ExitCode::UNSPECIFIED_ERROR;
    }
}

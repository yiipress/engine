<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Throwable;
use Yiisoft\Yii\Console\ExitCode;

use function is_int;

final class ExceptionExitCode
{
    private const MAX_EXIT_CODE = 254;

    public function resolve(Throwable $throwable): int
    {
        $code = $throwable->getCode();

        return is_int($code) && $code > ExitCode::OK && $code <= self::MAX_EXIT_CODE
            ? $code
            : ExitCode::UNSPECIFIED_ERROR;
    }
}

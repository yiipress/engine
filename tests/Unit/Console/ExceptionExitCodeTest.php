<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use YiiPress\Console\ExceptionExitCode;
use Yiisoft\Yii\Console\ExitCode;

final class ExceptionExitCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int}>
     */
    public static function codeProvider(): iterable
    {
        yield 'positive' => [ExitCode::USAGE, ExitCode::USAGE];
        yield 'maximum' => [254, 254];
        yield 'reserved' => [255, ExitCode::UNSPECIFIED_ERROR];
        yield 'too large' => [256, ExitCode::UNSPECIFIED_ERROR];
        yield 'zero' => [ExitCode::OK, ExitCode::UNSPECIFIED_ERROR];
        yield 'negative' => [-1, ExitCode::UNSPECIFIED_ERROR];
    }

    #[DataProvider('codeProvider')]
    public function testResolvesExceptionExitCode(int $code, int $expected): void
    {
        self::assertSame($expected, (new ExceptionExitCode())->resolve(new RuntimeException('Failure.', $code)));
    }
}

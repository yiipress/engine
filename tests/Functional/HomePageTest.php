<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Environment;
use HttpSoft\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class HomePageTest extends TestCase
{
    public function testHomePageReturnsOk(): void
    {
        $errorHandler = set_error_handler(static fn () => false);
        restore_error_handler();
        $exceptionHandler = set_exception_handler(null);
        restore_exception_handler();

        $runner = new HttpApplicationRunner(
            rootPath: dirname(__DIR__, 2),
            environment: Environment::appEnv(),
        );

        $response = $runner->runAndGetResponse(
            new ServerRequest(uri: '/'),
        );

        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        assertSame(200, $response->getStatusCode());
        assertStringContainsString(
            'Don\'t forget to check the guide',
            $body->getContents(),
        );

        while (true) {
            $current = set_error_handler(static fn () => false);
            restore_error_handler();
            if ($current === $errorHandler) {
                break;
            }
            restore_error_handler();
        }

        while (true) {
            $current = set_exception_handler(null);
            restore_exception_handler();
            if ($current === $exceptionHandler) {
                break;
            }
            restore_exception_handler();
        }
    }
}

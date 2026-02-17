<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Environment;
use FilesystemIterator;
use HttpSoft\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class HomePageTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = dirname(__DIR__, 2) . '/output';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->outputDir);
    }

    public function testHomePageBuildsAutomaticallyAndServesContent(): void
    {
        $this->removeDir($this->outputDir);

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
        assertStringContainsString('Hello', $body->getContents());

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

    public function testReturns404WhenOutputMissing(): void
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
            new ServerRequest(uri: '/nonexistent/'),
        );

        assertSame(404, $response->getStatusCode());

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

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}

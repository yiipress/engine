<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\TemplateContext;
use App\Build\TemplateResolver;
use App\Build\Theme;
use App\Build\ThemeRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class TemplateContextTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-partial-test-' . uniqid();
        mkdir($this->tempDir . '/partials', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testRendersPartialWithVariables(): void
    {
        file_put_contents(
            $this->tempDir . '/partials/greeting.php',
            'Hello, <?= htmlspecialchars($name) ?>!',
        );
        $context = $this->createContext();

        $result = $context->partial('greeting', ['name' => 'World']);

        assertSame('Hello, World!', $result);
    }

    public function testPartialHasIsolatedScope(): void
    {
        file_put_contents(
            $this->tempDir . '/partials/scoped.php',
            '<?= isset($outside) ? "leaked" : "isolated" ?>',
        );
        $outside = 'should not leak';
        $context = $this->createContext();

        $result = $context->partial('scoped');

        assertSame('isolated', $result);
    }

    public function testPartialCanCallNestedPartials(): void
    {
        file_put_contents(
            $this->tempDir . '/partials/outer.php',
            'before-<?= $partial("inner", ["x" => $value]) ?>-after',
        );
        file_put_contents(
            $this->tempDir . '/partials/inner.php',
            '<?= $x ?>',
        );
        $context = $this->createContext();

        $result = $context->partial('outer', ['value' => 'middle']);

        assertSame('before-middle-after', $result);
    }

    public function testThrowsForMissingPartial(): void
    {
        $context = $this->createContext();

        $this->expectException(RuntimeException::class);
        $context->partial('nonexistent');
    }

    public function testPartialEscapesHtmlByDefault(): void
    {
        file_put_contents(
            $this->tempDir . '/partials/escape.php',
            '<?= htmlspecialchars($text) ?>',
        );
        $context = $this->createContext();

        $result = $context->partial('escape', ['text' => '<script>alert(1)</script>']);

        assertStringContainsString('&lt;script&gt;', $result);
    }

    private function createContext(): TemplateContext
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('test', $this->tempDir));
        $resolver = new TemplateResolver($registry);
        return new TemplateContext($resolver, 'test');
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}

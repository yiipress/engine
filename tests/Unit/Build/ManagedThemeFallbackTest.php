<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;

use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

/**
 * A theme installed by `get:theme` resolves files through itself, its declared fallback and `local` only.
 */
final class ManagedThemeFallbackTest extends TestCase
{
    private const array MINIMAL_FALLBACK = [Theme::BUNDLED, Theme::LOCAL];
    private const array NO_FALLBACK = [Theme::LOCAL];

    private string $root;
    private string $minimal;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-managed-fallback-' . uniqid();
        mkdir($this->root, 0o755, true);
        $this->minimal = dirname(__DIR__, 3) . '/themes/minimal';
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testMissingTemplateComesFromMinimal(): void
    {
        $resolver = $this->resolver(new Theme('wonder', $this->theme('wonder', []), self::MINIMAL_FALLBACK));

        self::assertSame($this->minimal . '/entry.php', $resolver->resolve('entry', 'wonder'));
        self::assertSame($this->minimal . '/partials/footer.php', $resolver->resolvePartial('footer', 'wonder'));
    }

    public function testUnrelatedThemeIsNeverUsed(): void
    {
        $other = new Theme('aaa', $this->theme('aaa', ['special.php', 'partials/badge.php', 'assets/only.css']));
        $resolver = $this->resolver($other, new Theme('wonder', $this->theme('wonder', []), self::MINIMAL_FALLBACK));

        self::assertNull($resolver->resolveResource('assets/only.css', 'wonder'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Template "special" not found in themes: wonder, minimal.');
        $resolver->resolve('special', 'wonder');
    }

    public function testUnrelatedPartialIsNeverUsed(): void
    {
        $other = new Theme('aaa', $this->theme('aaa', ['partials/badge.php']));
        $resolver = $this->resolver($other, new Theme('wonder', $this->theme('wonder', []), self::MINIMAL_FALLBACK));

        $this->expectExceptionMessage('Template "partials/badge" not found in themes: wonder, minimal.');
        $resolver->resolvePartial('badge', 'wonder');
    }

    public function testLocalTemplatesComeAfterTheFallback(): void
    {
        $resolver = $this->resolver(
            new Theme('wonder', $this->theme('wonder', []), self::MINIMAL_FALLBACK),
            new Theme(Theme::LOCAL, $this->theme('local', ['entry.php', 'landing.php'])),
        );

        self::assertSame($this->minimal . '/entry.php', $resolver->resolve('entry', 'wonder'));
        self::assertSame($this->root . '/local/landing.php', $resolver->resolve('landing', 'wonder'));
    }

    public function testNoFallbackSkipsMinimalButKeepsLocal(): void
    {
        $resolver = $this->resolver(
            new Theme('wonder', $this->theme('wonder', ['entry.php']), self::NO_FALLBACK),
            new Theme(Theme::LOCAL, $this->theme('local', ['landing.php'])),
        );

        self::assertSame($this->root . '/wonder/entry.php', $resolver->resolve('entry', 'wonder'));
        self::assertSame($this->root . '/local/landing.php', $resolver->resolve('landing', 'wonder'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Template "partials/footer" not found in themes: wonder, local.');
        $resolver->resolvePartial('footer', 'wonder');
    }

    public function testResourcesFollowTheSameChain(): void
    {
        $withMinimal = $this->resolver(new Theme('wonder', $this->theme('wonder', []), self::MINIMAL_FALLBACK));
        $without = $this->resolver(new Theme('wonder', $this->theme('bare', []), self::NO_FALLBACK));

        self::assertSame(
            $this->minimal . '/translation/en.yaml',
            $withMinimal->resolveResource('translation/en.yaml', 'wonder'),
        );
        self::assertSame(Theme::BUNDLED, $withMinimal->resolveResourceThemeName('assets/style.css', 'wonder'));
        self::assertNull($without->resolveResource('translation/en.yaml', 'wonder'));
        self::assertSame('wonder', $without->resolveResourceThemeName('assets/style.css', 'wonder'));
    }

    public function testLegacyThemeKeepsFallingBackToEveryUnmanagedTheme(): void
    {
        $resolver = $this->resolver(
            new Theme('aaa', $this->theme('aaa', ['special.php'])),
            new Theme('mine', $this->theme('mine', [])),
            new Theme('wonder', $this->theme('wonder', ['managed.php']), self::MINIMAL_FALLBACK),
        );

        self::assertSame($this->root . '/aaa/special.php', $resolver->resolve('special', 'mine'));
        self::assertSame($this->minimal . '/entry.php', $resolver->resolve('entry', 'mine'));
        $this->expectException(RuntimeException::class);
        $resolver->resolve('managed', 'mine');
    }

    private function resolver(Theme ...$themes): TemplateResolver
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme(Theme::BUNDLED, $this->minimal));
        foreach ($themes as $theme) {
            $registry->register($theme);
        }

        return new TemplateResolver($registry);
    }

    /**
     * @param list<string> $files
     */
    private function theme(string $name, array $files): string
    {
        $directory = $this->root . '/' . $name;
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0o755, true);
            }
            file_put_contents($path, '<?php // ' . $name);
        }
        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        return $directory;
    }
}

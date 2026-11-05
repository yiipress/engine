<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WeakReference;
use YiiPress\Build\AssetFingerprintManifest;
use YiiPress\Build\AssetUrlRewriter;

use function PHPUnit\Framework\assertNotFalse;
use function PHPUnit\Framework\assertNotSame;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

final class AssetUrlRewriterAdmissionTest extends TestCase
{
    public function testRewriterMatchesMixedEncodedDirectoryAndNumericFilename(): void
    {
        $manifest = new AssetFingerprintManifest();
        for ($i = 0; $i < 12; $i++) {
            $manifest->register('assets/style-' . $i . '.css', __FILE__);
        }
        $resolved = $manifest->resolve('assets/style-11.css');
        $html = '<link href="../../../%61ssets/st%79le-%31%31.c%73s?q=%2f#part">';

        assertSame(
            '<link href="../../../' . $resolved . '?q=%2f#part">',
            new AssetUrlRewriter($manifest)->rewrite($html, '../../../'),
        );
    }

    public function testRewriterPreservesUnregisteredNumericVariantsAndFingerprintedPaths(): void
    {
        $manifest = new AssetFingerprintManifest();
        for ($i = 0; $i < 12; $i++) {
            $manifest->register('assets/style-' . $i . '.css', __FILE__);
        }
        $html = '<link href="../assets/style-%39%39.css">'
            . '<link href="../assets/style-000.css">'
            . '<link href="../' . $manifest->resolve('assets/style-0.css') . '">';

        assertSame($html, new AssetUrlRewriter($manifest)->rewrite($html, '../'));
    }

    public function testRewriterPreservesInvalidUtf8KeysAndLaterRegistrations(): void
    {
        $manifest = new AssetFingerprintManifest();
        $resolved = $manifest->register("assets/high\xff.png", __FILE__);
        $rewriter = new AssetUrlRewriter($manifest);

        assertSame('<img src="' . $resolved . '">', $rewriter->rewrite("<img src=\"assets/high\xff.png\">"));
        $later = $manifest->register('assets/later.css', __FILE__);
        assertSame(
            '<link href="' . $later . '">',
            new AssetUrlRewriter($manifest)->rewrite('<link href="assets/%6cater.css">'),
        );
    }

    public function testFreshRewritersSeeAddedAndReplacedRegistrations(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'yiipress-admission-');
        assertNotFalse($source);
        try {
            file_put_contents($source, 'first');
            $manifest = new AssetFingerprintManifest();
            $manifest->register('assets/first.css', $source);
            $html = '<link href="../assets/%6cater.css">';
            assertSame($html, new AssetUrlRewriter($manifest)->rewrite($html, '../'));

            $first = $manifest->register('assets/later.css', $source);
            assertSame('<link href="../' . $first . '">', new AssetUrlRewriter($manifest)->rewrite($html, '../'));
            file_put_contents($source, 'second');
            $second = $manifest->register('assets/later.css', $source);
            assertNotSame($first, $second);
            assertSame('<link href="../' . $second . '">', new AssetUrlRewriter($manifest)->rewrite($html, '../'));
        } finally {
            unlink($source);
        }
    }

    public function testSharedAdmissionCacheDoesNotRetainManifest(): void
    {
        $manifest = new AssetFingerprintManifest();
        $resolved = $manifest->register('assets/weak.css', __FILE__);
        $weak = WeakReference::create($manifest);
        for ($i = 0; $i < 3; $i++) {
            assertSame(
                '<link href="' . $resolved . '">',
                new AssetUrlRewriter($manifest)->rewrite('<link href="assets/%77eak.css">'),
            );
        }
        unset($manifest);
        gc_collect_cycles();

        assertNull($weak->get());
    }

    /** @return iterable<string, array{string}> */
    public static function fallbackProvider(): iterable
    {
        foreach (['slashless', 'trailing', 'component', 'states', 'expression', 'keys'] as $shape) {
            yield $shape => [$shape];
        }
    }

    #[DataProvider('fallbackProvider')]
    public function testUncoveredOrOverBudgetManifestKeepsAllEntriesVisible(string $shape): void
    {
        $manifest = new AssetFingerprintManifest();
        $resolved = $manifest->register('assets/covered.css', __FILE__);
        if ($shape === 'slashless' || $shape === 'trailing') {
            $path = $shape === 'slashless' ? 'plain.css' : 'assets/';
            $uncovered = $manifest->register($path, __FILE__);
            assertSame(
                '<link href="' . $uncovered . '">',
                new AssetUrlRewriter($manifest)->rewrite('<link href="' . $path . '">'),
            );
        } elseif ($shape === 'component') {
            $manifest->register('assets/' . str_repeat('x', 65) . '.css', __FILE__);
        } elseif ($shape === 'keys') {
            for ($i = 0; $i < 3000; $i++) {
                $manifest->register('assets/theme/style-' . $i . '.css', __FILE__);
            }
        } else {
            $count = $shape === 'states' ? 100 : 30;
            for ($i = 0; $i < $count; $i++) {
                $letters = strtr(
                    base_convert((string) $i, 10, 26),
                    '0123456789abcdefghijklmnop',
                    'abcdefghijklmnopqrstuvwxyz',
                );
                $manifest->register(
                    'assets/' . str_repeat('q', 42) . str_pad($letters, 3, 'a', STR_PAD_LEFT) . '.css',
                    __FILE__,
                );
            }
        }

        assertSame(
            '<link href="' . $resolved . '">',
            new AssetUrlRewriter($manifest)->rewrite('<link href="assets/%63overed.css">'),
        );
    }

    public function testRewriterRecoversAfterPcreWorkLimit(): void
    {
        $manifest = new AssetFingerprintManifest();
        $resolved = $manifest->register('assets/limited.css', __FILE__);
        $rewriter = new AssetUrlRewriter($manifest);
        $html = '<link href="assets/%6cimited.css">';
        $jit = ini_get('pcre.jit');
        $limit = ini_get('pcre.backtrack_limit');
        try {
            ini_set('pcre.jit', '0');
            ini_set('pcre.backtrack_limit', '1');
            assertSame($html, $rewriter->rewrite($html));
        } finally {
            ini_set('pcre.jit', $jit);
            ini_set('pcre.backtrack_limit', $limit);
        }

        assertSame('<link href="' . $resolved . '">', new AssetUrlRewriter($manifest)->rewrite($html));
    }

    /** @return iterable<string, array{int}> */
    public static function controlProvider(): iterable
    {
        foreach (array_merge(range(0, 31), [127]) as $byte) {
            yield (string) $byte => [$byte];
        }
    }

    #[DataProvider('controlProvider')]
    public function testAbsoluteControlAliasPreservesNoAdmission(int $byte): void
    {
        $manifest = new AssetFingerprintManifest();
        $manifest->register('assets/a_b.png', __FILE__);
        $manifest->register('assets/style-0.css', __FILE__);
        $alias = '<img src="https://example.com/site/assets/a' . chr($byte) . 'b.png?q=plain">';
        $html = $alias . '<img src="unrelated/a_b.png"><link href="unrelated/style-9.css">';

        assertSame($html, new AssetUrlRewriter($manifest)->rewrite($html, 'https://example.com/site/'));
    }

    #[DataProvider('controlProvider')]
    public function testAbsoluteControlAliasUsesWholePagePercentAdmission(int $byte): void
    {
        $manifest = new AssetFingerprintManifest();
        $resolved = $manifest->register('assets/a_b.png', __FILE__);
        $root = 'https://example.com/site/';
        $html = '<img src="' . $root . 'assets/a' . chr($byte) . 'b.png"><p>100% other text</p>';

        assertSame(
            '<img src="' . $root . $resolved . '"><p>100% other text</p>',
            new AssetUrlRewriter($manifest)->rewrite($html, $root),
        );
    }

    public function testAbsoluteDirectoryControlAliasUsesWholePageLiteralAdmission(): void
    {
        $manifest = new AssetFingerprintManifest();
        $resolved = $manifest->register('assets/a_b/photo.png', __FILE__);
        $manifest->register('assets/trigger.css', __FILE__);
        $root = 'HTTPS://EXAMPLE.COM:443/site/';
        $html = '<img src="https://example.com/site/assets/a' . "\n" . 'b/photo.png?q=plain"><p>assets/trigger.css</p>';

        assertSame(
            '<img src="' . $root . $resolved . '?q=plain"><p>assets/trigger.css</p>',
            new AssetUrlRewriter($manifest)->rewrite($html, $root),
        );
    }

    public function testRelativeUrlUnderAbsoluteRootPreservesWholePageAdmission(): void
    {
        $manifest = new AssetFingerprintManifest();
        $manifest->register('assets/a_b.png', __FILE__);
        $html = '<img src="../assets/a' . "\n" . 'b.png?q=plain"><img src="unrelated/a_b.png">';

        assertSame($html, new AssetUrlRewriter($manifest)->rewrite($html, 'https://example.com/site/'));
    }
}

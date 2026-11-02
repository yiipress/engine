<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\AssetFingerprintManifest;
use YiiPress\Build\AssetUrlRewriter;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use RuntimeException;

use function PHPUnit\Framework\assertNotSame;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class AssetFingerprintManifestTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-asset-fingerprint-test-' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testRegisterGeneratesFingerprintedPath(): void
    {
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'body{color:red}');

        $manifest = new AssetFingerprintManifest();
        $resolved = $manifest->register('assets/theme/style.css', $source);

        assertNotSame('assets/theme/style.css', $resolved);
        assertStringContainsString('assets/theme/style.', $resolved);
        assertSame($resolved, $manifest->resolve('assets/theme/style.css'));
    }

    public function testLogicalPathMatcherUsesLiteralCaseSensitivePaths(): void
    {
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'css');
        $manifest = new AssetFingerprintManifest();
        assertSame(false, $manifest->containsLogicalPath('assets/a+[b]~.css'));
        $manifest->register('assets/a+[b]~.css', $source);

        assertSame(true, $manifest->containsLogicalPath('<link href="/assets/a+[b]~.css?v=1">'));
        assertSame(false, $manifest->containsLogicalPath('assets/A+[b]~.css'));
        assertSame(false, $manifest->containsLogicalPath('assets/aaab~Xcss'));
        assertSame(false, $manifest->containsLogicalPath(''));
    }

    public function testRewriterSeesPathsRegisteredAfterFirstRewrite(): void
    {
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'css');
        $manifest = new AssetFingerprintManifest();
        $manifest->register('assets/first.css', $source);
        $rewriter = new AssetUrlRewriter($manifest);
        $html = '<link href="assets/second.css">';
        assertSame($html, $rewriter->rewrite($html));

        $fingerprinted = $manifest->register('assets/second.css', $source);
        assertSame('<link href="' . $fingerprinted . '">', $rewriter->rewrite($html));
        assertSame(true, $manifest->containsLogicalPath('assets/first.css'));
    }

    public function testLogicalPathMatcherFallsBackForOversizedPatterns(): void
    {
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'css');
        $manifest = new AssetFingerprintManifest();
        for ($i = 0; $i < 1000; $i++) {
            $manifest->register('assets/' . str_repeat('a', 200) . $i . '.css', $source);
        }
        $lastPath = 'assets/' . str_repeat('a', 200) . '999.css';
        assertSame(true, $manifest->containsLogicalPath($lastPath));
        assertSame(false, $manifest->containsLogicalPath('no assets here'));
        assertSame(true, $manifest->containsLogicalPath($lastPath));
        assertSame(
            '<link href="' . $manifest->resolve($lastPath) . '">',
            new AssetUrlRewriter($manifest)->rewrite('<link href="' . $lastPath . '">'),
        );

        $manifest->register('assets/late.css', $source);
        assertSame(true, $manifest->containsLogicalPath('assets/late.css'));
    }

    public function testRegisterRejectsMissingSource(): void
    {
        $manifest = new AssetFingerprintManifest();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to hash asset source file:');

        $manifest->register('assets/theme/missing.css', $this->tempDir . '/missing.css');
    }

    public function testRewriterUpdatesRelativeAndAbsoluteAssetUrls(): void
    {
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'body{color:red}');

        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('assets/theme/style.css', $source);
        $rewriter = new AssetUrlRewriter($manifest);

        $relative = $rewriter->rewrite('<link href="../../assets/theme/style.css">', '../../');
        $absolute = $rewriter->rewrite('<link href="/assets/theme/style.css">', '../../');

        assertSame('<link href="../../' . $fingerprinted . '">', $relative);
        assertSame('<link href="../../' . $fingerprinted . '">', $absolute);
    }

    public function testRewriterRelativizesRootRelativeContentAssetUrls(): void
    {
        $source = $this->tempDir . '/photo.jpg';
        file_put_contents($source, 'jpg');

        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('blog/assets/photo.jpg', $source);
        $rewriter = new AssetUrlRewriter($manifest);

        $html = $rewriter->rewrite('<img src="/blog/assets/photo.jpg">', '../../');

        assertSame('<img src="../../' . $fingerprinted . '">', $html);
    }

    /** @return iterable<string, array{string}> */
    public static function absoluteRootProvider(): iterable
    {
        yield 'lowercase root' => ['https://example.com/site/'];
        yield 'uppercase scheme and host' => ['HTTPS://EXAMPLE.COM/site/'];
        yield 'root without trailing slash' => ['https://example.com/site'];
        yield 'uppercase root without trailing slash' => ['HTTPS://EXAMPLE.COM/site'];
        yield 'explicit default port' => ['https://example.com:443/site/'];
    }

    #[DataProvider('absoluteRootProvider')]
    public function testRewriterFingerprintsOnlyMatchingAbsoluteSiteUrls(string $rootPath): void
    {
        $source = $this->tempDir . '/photo.jpg';
        file_put_contents($source, 'jpg');
        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('assets/photo.jpg', $source);
        $expectedRoot = rtrim($rootPath, '/') . '/';
        $html = '<img src="https://example.com/site/assets/photo.jpg?q=1#part">'
            . '<img src=https://example.com/site/assets/photo.jpg>'
            . '<img src=HTTPS://EXAMPLE.COM/site/assets/photo.jpg?q=1#part>'
            . '<img src=https://example.com/Site/assets/photo.jpg>'
            . '<img src=https://cdn.example.com/site/assets/photo.jpg>'
            . '<img src=https://example.com.evil.test/site/assets/photo.jpg>'
            . '<img src=https://example.com:8443/site/assets/photo.jpg>'
            . '<img src=https://example.com/site-other/assets/photo.jpg>'
            . '<img src=https://example.com/siteassets/photo.jpg>'
            . '<img src=http://example.com/site/assets/photo.jpg>'
            . '<img src=//example.com/site/assets/photo.jpg>';

        $rewritten = new AssetUrlRewriter($manifest)->rewrite($html, $rootPath);

        assertSame(
            '<img src="' . $expectedRoot . $fingerprinted . '?q=1#part">'
                . '<img src=' . $expectedRoot . $fingerprinted . '>'
                . '<img src=' . $expectedRoot . $fingerprinted . '?q=1#part>'
                . '<img src=https://example.com/Site/assets/photo.jpg>'
                . '<img src=https://cdn.example.com/site/assets/photo.jpg>'
                . '<img src=https://example.com.evil.test/site/assets/photo.jpg>'
                . '<img src=https://example.com:8443/site/assets/photo.jpg>'
                . '<img src=https://example.com/site-other/assets/photo.jpg>'
                . '<img src=https://example.com/siteassets/photo.jpg>'
                . '<img src=http://example.com/site/assets/photo.jpg>'
                . '<img src=//example.com/site/assets/photo.jpg>',
            $rewritten,
        );
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function defaultPortProvider(): iterable
    {
        yield 'HTTPS explicit asset port' => ['https://example.com/site/', 'https://example.com:443/site/', true];
        yield 'HTTPS explicit root port' => ['https://example.com:443/site/', 'https://example.com/site/', true];
        yield 'HTTP explicit asset port' => ['http://example.com/site/', 'http://example.com:80/site/', true];
        yield 'HTTP explicit root port' => ['http://example.com:80/site/', 'http://example.com/site/', true];
        yield 'mixed case default port' => ['HTTPS://EXAMPLE.COM/site', 'https://example.com:443/site/', true];
        yield 'same nondefault port' => ['https://example.com:8443/site/', 'https://example.com:8443/site/', true];
        yield 'different nondefault port' => ['https://example.com/site/', 'https://example.com:8443/site/', false];
        yield 'nondefault root port' => ['https://example.com:8443/site/', 'https://example.com/site/', false];
        yield 'different schemes' => ['http://example.com:80/site/', 'https://example.com:443/site/', false];
    }

    #[DataProvider('defaultPortProvider')]
    public function testRewriterMatchesDefaultPorts(string $rootPath, string $assetRoot, bool $matches): void
    {
        $source = $this->tempDir . '/photo.jpg';
        file_put_contents($source, 'jpg');
        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('assets/photo.jpg', $source);
        $html = '<img src="' . $assetRoot . 'assets/photo.jpg?q=1#part">';

        $rewritten = new AssetUrlRewriter($manifest)->rewrite($html, $rootPath);

        $expected = $matches
            ? '<img src="' . rtrim($rootPath, '/') . '/' . $fingerprinted . '?q=1#part">'
            : $html;
        assertSame($expected, $rewritten);
    }

    public function testRewriterPrefixesUnqualifiedAssetUrlsWithRootPath(): void
    {
        $source = $this->tempDir . '/mermaid.css';
        file_put_contents($source, '.mermaid{}');

        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('assets/plugins/mermaid.css', $source);
        $rewriter = new AssetUrlRewriter($manifest);

        $html = $rewriter->rewrite('<link href="assets/plugins/mermaid.css">', '../');

        assertSame('<link href="../' . $fingerprinted . '">', $html);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function absoluteDotSegmentProvider(): iterable
    {
        yield 'parent segment' => ['https://example.com/site/blog/../assets/photo.jpg', true];
        yield 'current segment inside asset path' => ['https://example.com/site/assets/./photo.jpg', true];
        yield 'enter base after normalization' => ['https://example.com/other/../site/assets/photo.jpg', true];
        yield 'leave base after normalization' => ['https://example.com/site/../assets/photo.jpg', false];
        yield 'sibling after normalization' => ['https://example.com/site/../site-other/assets/photo.jpg', false];
        yield 'external origin' => ['https://cdn.example.com/site/blog/../assets/photo.jpg', false];
        yield 'parents above root' => ['https://example.com/../../site/assets/photo.jpg', true];
        yield 'empty segment removed by parent' => ['https://example.com/site/blog//../assets/photo.jpg', false];
    }

    #[DataProvider('absoluteDotSegmentProvider')]
    public function testRewriterNormalizesAbsoluteDotSegmentsBeforeMatchingBase(string $url, bool $matches): void
    {
        $source = $this->tempDir . '/photo.jpg';
        file_put_contents($source, 'jpg');
        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('assets/photo.jpg', $source);
        $html = '<img src="' . $url . '?q=1#part">';

        $actual = new AssetUrlRewriter($manifest)->rewrite($html, 'https://example.com/site/');

        assertSame($matches ? '<img src="https://example.com/site/' . $fingerprinted . '?q=1#part">' : $html, $actual);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function encodedAssetProvider(): iterable
    {
        yield 'space' => ['assets/my photo.jpg', 'assets/my%20photo.jpg', ''];
        yield 'absolute space' => [
            'assets/my photo.jpg',
            'https://example.com/site/assets/my%20photo.jpg',
            'https://example.com/site/',
        ];
        yield 'relative space' => ['assets/my photo.jpg', '../assets/my%20photo.jpg', '../'];
        yield 'query in filename' => ['assets/my?photo.jpg', '/assets/my%3Fphoto.jpg', '../'];
        yield 'fragment in filename' => ['assets/my#photo.jpg', '/assets/my%23photo.jpg', '../'];
        yield 'literal percent' => ['assets/my%20photo.jpg', '/assets/my%2520photo.jpg', '../'];
        yield 'plus is not space' => ['assets/my+photo.jpg', '/assets/my%2Bphoto.jpg', '../'];
    }

    #[DataProvider('encodedAssetProvider')]
    public function testRewriterMatchesEncodedAssetPaths(string $logicalPath, string $url, string $rootPath): void
    {
        $source = $this->tempDir . '/photo.jpg';
        file_put_contents($source, 'jpg');
        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register($logicalPath, $source);
        $encoded = str_replace('%2F', '/', rawurlencode($fingerprinted));
        $html = '<img src="' . $url . '?q=1&amp;x=2#part">';

        $actual = new AssetUrlRewriter($manifest)->rewrite($html, $rootPath);

        assertSame('<img src="' . $rootPath . $encoded . '?q=1&amp;x=2#part">', $actual);
    }

    public function testRewriterMatchesEncodedRelativeAssetWithoutSuffix(): void
    {
        $source = $this->tempDir . '/photo.jpg';
        file_put_contents($source, 'jpg');
        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('assets/my photo.jpg', $source);

        $actual = new AssetUrlRewriter($manifest)->rewrite('<img src="../assets/my%20photo.jpg">', '../');

        assertSame('<img src="../' . str_replace(' ', '%20', $fingerprinted) . '">', $actual);
    }

    public function testRewriterPreservesEncodedSeparatorsAndUnmatchedUrls(): void
    {
        $source = $this->tempDir . '/photo.jpg';
        file_put_contents($source, 'jpg');
        $manifest = new AssetFingerprintManifest();
        $manifest->register('assets/my photo.jpg', $source);
        $manifest->register('assets/my+photo.jpg', $source);
        $html = '<img src="/assets%2Fmy%20photo.jpg">'
            . '<img src="/assets%5cmy%20photo.jpg">'
            . '<img src="https://cdn.example.com/site/assets/my%20photo.jpg">'
            . '<img src="https://example.com/site-other/assets/my%20photo.jpg">'
            . '<img src="/assets/missing%20photo.jpg">'
            . '<img src="/assets/my%ZZphoto.jpg">';

        $actual = new AssetUrlRewriter($manifest)->rewrite($html, 'https://example.com/site/');

        assertSame($html, $actual);
    }

    public function testRewriterKeepsExactLiteralManifestMatchBeforeDecoding(): void
    {
        $source = $this->tempDir . '/photo.jpg';
        file_put_contents($source, 'jpg');
        $manifest = new AssetFingerprintManifest();
        $manifest->register('assets/my photo.jpg', $source);
        $literal = $manifest->register('assets/my%20photo.jpg', $source);

        $actual = new AssetUrlRewriter($manifest)->rewrite('<img src="assets/my%20photo.jpg">');

        assertSame('<img src="' . $literal . '">', $actual);
    }

    public function testRewriterSkipsHtmlWithoutAssetReferences(): void
    {
        $manifest = new AssetFingerprintManifest();
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'body{color:red}');
        $manifest->register('assets/theme/style.css', $source);
        $rewriter = new AssetUrlRewriter($manifest);

        $html = '<div><p>No local asset URLs here.</p></div>';

        assertSame($html, $rewriter->rewrite($html, '../../'));
    }

    public function testRewriterSkipsHtmlWithAlreadyFingerprintedAssetUrls(): void
    {
        $manifest = new AssetFingerprintManifest();
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'body{color:red}');
        $fingerprinted = $manifest->register('assets/theme/style.css', $source);
        $rewriter = new AssetUrlRewriter($manifest);

        $html = '<link href="../../' . $fingerprinted . '">';

        assertSame($html, $rewriter->rewrite($html, '../../'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rawHtmlProvider(): iterable
    {
        yield 'unquoted' => ['<img src=../assets/logo.svg>', '<img src=../%s>'];
        yield 'spaced quoted' => ['<img src = "../assets/logo.svg">', '<img src = "../%s">'];
        yield 'uppercase spaced' => ['<img SRC = ../assets/logo.svg>', '<img SRC = ../%s>'];
        yield 'query and fragment' => ['<img src=../assets/logo.svg?q=1#part>', '<img src=../%s?q=1#part>'];
        yield 'quoted boundary' => ['<img title="a > b" src=../assets/logo.svg>', '<img title="a > b" src=../%s>'];
        yield 'multiple URLs with a literal attribute and body' => [
            '<a href=../assets/logo.svg src="../assets/logo.svg?q=1#part" title="src=../assets/logo.svg">'
                . 'href=../assets/logo.svg</a>',
            '<a href=../%s src="../%s?q=1#part" title="src=../assets/logo.svg">'
                . 'href=../assets/logo.svg</a>',
        ];
        yield 'tag text inside a non-URL attribute' => [
            '<div title="<img src=../assets/logo.svg>"></div><img src=../assets/logo.svg>',
            '<div title="<img src=../assets/logo.svg>"></div><img src=../%s>',
        ];
        yield 'empty URL before an asset URL' => [
            '<a href="" src=../assets/logo.svg>asset</a>',
            '<a href="" src=../%s>asset</a>',
        ];
        yield 'custom element boundary' => [
            '<script-demo></script-demo><img src=../assets/logo.svg>',
            '<script-demo></script-demo><img src=../%s>',
        ];
        yield 'data attribute' => [
            '<div data-src="../assets/logo.svg"></div>',
            '<div data-src="../assets/logo.svg"></div>',
        ];
        yield 'attribute text' => [
            '<div title="src=../assets/logo.svg"></div>',
            '<div title="src=../assets/logo.svg"></div>',
        ];
        yield 'script text' => [
            '<script>let x = \'<img src="../assets/logo.svg">\';</script>',
            '<script>let x = \'<img src="../assets/logo.svg">\';</script>',
        ];
        yield 'comment text' => [
            '<!-- <img src="../assets/logo.svg"> -->',
            '<!-- <img src="../assets/logo.svg"> -->',
        ];
        yield 'protocol-relative' => [
            '<img src=//cdn.example.com/assets/logo.svg>',
            '<img src=//cdn.example.com/assets/logo.svg>',
        ];
        yield 'external' => [
            '<img src=https://example.com/assets/logo.svg>',
            '<img src=https://example.com/assets/logo.svg>',
        ];
        yield 'CDATA text and real image' => [
            '<svg><![CDATA[<img src=../assets/logo.svg>]]></svg><img src=../assets/logo.svg>',
            '<svg><![CDATA[<img src=../assets/logo.svg>]]></svg><img src=../%s>',
        ];
        foreach (['script', 'style', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes'] as $tag) {
            $raw = '<' . $tag . '><img src=../assets/logo.svg></' . $tag . ' data-x="a>b">';
            yield $tag . ' attributed closing tag' => [
                $raw . '<img src=../assets/logo.svg>',
                $raw . '<img src=../%s>',
            ];
        }
    }

    #[DataProvider('rawHtmlProvider')]
    public function testRewriterPreservesRawHtmlBoundaries(string $html, string $expected): void
    {
        $source = $this->tempDir . '/logo.svg';
        file_put_contents($source, 'svg');
        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('assets/logo.svg', $source);

        $actual = new AssetUrlRewriter($manifest)->rewrite($html, '../');

        assertSame(str_replace('%s', $fingerprinted, $expected), $actual);
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

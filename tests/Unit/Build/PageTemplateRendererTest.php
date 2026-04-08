<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\AssetFingerprintManifest;
use App\Build\PageTemplateRenderer;
use App\Build\Theme;
use App\Build\ThemeRegistry;
use App\Build\TemplateResolver;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function PHPUnit\Framework\assertStringContainsString;
use function sys_get_temp_dir;

final class PageTemplateRendererTest extends TestCase
{
    private string $tempDir;
    private string $assetFile;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress_page_template_renderer_test_' . uniqid();
        mkdir($this->tempDir . '/theme/partials', 0o755, true);

        file_put_contents($this->tempDir . '/theme/page.php', <<<'PHP'
<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html>
<head><?= $partial('head', ['title' => $title, 'rootPath' => $rootPath]) ?></head>
<body><a href="assets/theme/style.css">asset</a><h1><?= $title ?></h1></body>
</html>
PHP);
        file_put_contents($this->tempDir . '/theme/partials/head.php', <<<'PHP'
<?php declare(strict_types=1); ?>
<title><?= $title ?></title>
PHP);
        $this->assetFile = $this->tempDir . '/style.css';
        file_put_contents($this->assetFile, 'body{}');
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, FilesystemIterator::SKIP_DOTS),
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

        rmdir($this->tempDir);
    }

    public function testRenderRendersTemplateAndRelativizesHtml(): void
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('test', $this->tempDir . '/theme'));

        $manifest = new AssetFingerprintManifest();
        $manifest->register('assets/theme/style.css', $this->assetFile);

        $renderer = new PageTemplateRenderer(new TemplateResolver($registry), 'test', $manifest);
        $html = $renderer->render('page', [
            'title' => 'Example',
            'rootPath' => '../',
        ], '../');

        assertStringContainsString('<title>Example</title>', $html);
        assertStringContainsString('<h1>Example</h1>', $html);
        assertStringContainsString('href="' . $manifest->resolve('assets/theme/style.css') . '"', $html);
    }
}

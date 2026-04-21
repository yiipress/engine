<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\PageTemplateRenderer;
use App\Build\TemplateResolver;
use App\Build\Theme;
use App\Build\ThemeRegistry;
use App\I18n\UiText;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function PHPUnit\Framework\assertStringContainsString;

final class PageTemplateRendererTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-page-template-renderer-' . uniqid();
        mkdir($this->tempDir . '/partials', 0o755, true);

        file_put_contents(
            $this->tempDir . '/example.php',
            <<<'PHP'
<?php

declare(strict_types=1);
?>
<h1><?= $h($t('search')) ?></h1>
<?= $partial('message', ['ui' => $ui]) ?>
PHP,
        );

        file_put_contents(
            $this->tempDir . '/partials/message.php',
            <<<'PHP'
<?php

declare(strict_types=1);
?>
<p><?= $h($t('next')) ?></p>
PHP,
        );
    }

    protected function tearDown(): void
    {
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

    public function testProvidesTranslationHelperToTemplatesAndPartials(): void
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('test', $this->tempDir));
        $registry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));
        $resolver = new TemplateResolver($registry);
        $renderer = new PageTemplateRenderer($resolver, 'test');

        $html = $renderer->render('example', [
            'ui' => UiText::forTheme('ru', $resolver, 'minimal'),
        ], './');

        assertStringContainsString('<h1>Поиск</h1>', $html);
        assertStringContainsString('<p>Далее</p>', $html);
    }
}

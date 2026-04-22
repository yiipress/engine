<?php

declare(strict_types=1);

namespace App\Tests\Unit\I18n;

use App\Build\TemplateResolver;
use App\Build\Theme;
use App\Build\ThemeRegistry;
use App\I18n\UiText;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertArrayHasKey;

final class UiTextTest extends TestCase
{
    public function testLoadsTranslationsFromThemeFiles(): void
    {
        $ui = UiText::forTheme('ru', $this->createTemplateResolver(), 'minimal');

        assertSame('Поиск', $ui->get('search'));
        assertSame('Закрыть поиск', $ui->get('search_close'));
        assertSame('Страница 2 из 5', $ui->get('page_of', ['current' => 2, 'total' => 5]));
        assertSame('Март', $ui->monthName(3));
    }

    public function testFallsBackToEnglishThemeTranslationForUnknownLanguage(): void
    {
        $ui = UiText::forTheme('de', $this->createTemplateResolver(), 'minimal');

        assertSame('Search', $ui->get('search'));
        assertSame('Categories', $ui->taxonomyLabel('categories'));
    }

    public function testFallsBackToDefaultLanguageBeforeEnglishOrLabel(): void
    {
        $themePath = sys_get_temp_dir() . '/yiipress-ui-text-theme-' . uniqid();
        mkdir($themePath . '/translation', 0o755, true);
        file_put_contents($themePath . '/translation/en.yaml', "search: Search\n");
        file_put_contents($themePath . '/translation/ru.yaml', "search: Поиск\ntaxonomy.categories: Категории\n");

        try {
            $registry = new ThemeRegistry();
            $registry->register(new Theme('fallback', $themePath));
            $ui = UiText::forTheme('de', new TemplateResolver($registry), 'fallback', 'ru');

            assertSame('Поиск', $ui->get('search'));
            assertSame('Категории', $ui->taxonomyLabel('categories'));
        } finally {
            $this->removeDir($themePath);
        }
    }

    public function testNormalizesRegionalLanguageCodesToBaseTranslation(): void
    {
        $ui = UiText::forTheme('ru-RU', $this->createTemplateResolver(), 'minimal');

        assertSame('Поиск', $ui->get('search'));
        assertSame('Теги', $ui->taxonomyLabel('tags'));
    }

    public function testCanExportCatalogsForMultipleUiLanguages(): void
    {
        $catalogs = UiText::catalogsForTheme(['en', 'ru'], $this->createTemplateResolver(), 'minimal', 'en');

        assertArrayHasKey('en', $catalogs);
        assertArrayHasKey('ru', $catalogs);
        assertSame('Interface language', $catalogs['en']['ui_language']);
        assertSame('Язык интерфейса', $catalogs['ru']['ui_language']);
    }

    public function testFallsBackToKeyWhenThemeHasNoTranslationFiles(): void
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('empty', sys_get_temp_dir()));
        $ui = UiText::forTheme('ru', new TemplateResolver($registry), 'empty');

        assertSame('search', $ui->get('search'));
        assertSame('Categories', $ui->taxonomyLabel('categories'));
        assertSame('Март', $ui->monthName(3));
    }

    private function createTemplateResolver(): TemplateResolver
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));

        return new TemplateResolver($registry);
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

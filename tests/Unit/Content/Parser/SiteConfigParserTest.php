<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Parser;

use App\Content\Parser\SiteConfigParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertTrue;

final class SiteConfigParserTest extends TestCase
{
    public function testParseSiteConfig(): void
    {
        $parser = new SiteConfigParser();
        $dataDir = dirname(__DIR__, 3) . '/Support/Data/content';

        $config = $parser->parse($dataDir . '/config.yaml');

        assertSame('Test Site', $config->title);
        assertSame('A test site', $config->description);
        assertSame('https://test.example.com', $config->baseUrl);
        assertSame('en', $config->defaultLanguage);
        assertSame('UTF-8', $config->charset);
        assertSame('john-doe', $config->defaultAuthor);
        assertSame('F j, Y', $config->dateFormat);
        assertSame(5, $config->entriesPerPage);
        assertSame('/:collection/:slug/', $config->permalink);
        assertSame(['tags', 'categories'], $config->taxonomies);
        assertSame(['github_url' => 'https://github.com/test'], $config->params);
        assertSame('local', $config->theme);
        assertSame('Solarized (dark)', $config->highlightTheme);
        assertTrue($config->assets->fingerprint);
    }

    public function testParseAssetConfigCanDisableFingerprinting(): void
    {
        $filePath = sys_get_temp_dir() . '/yiipress-site-config-' . uniqid() . '.yaml';
        file_put_contents($filePath, "title: Test\nlanguages: [en]\nassets:\n  fingerprint: false\n");

        $parser = new SiteConfigParser();
        $config = $parser->parse($filePath);

        assertFalse($config->assets->fingerprint);

        unlink($filePath);
    }

    public function testUsesFirstLanguageAsI18nDefaultLanguage(): void
    {
        $filePath = sys_get_temp_dir() . '/yiipress-site-config-' . uniqid() . '.yaml';
        file_put_contents($filePath, "title: Test\nlanguages: [ru, en, de]\n");

        $parser = new SiteConfigParser();
        $config = $parser->parse($filePath);

        assertNotNull($config->i18n);
        assertSame('ru', $config->defaultLanguage);
        assertSame('ru', $config->i18n->defaultLanguage);
        assertSame(['ru', 'en', 'de'], $config->i18n->languages);

        unlink($filePath);
    }

    public function testThrowsWhenLanguagesMissing(): void
    {
        $filePath = sys_get_temp_dir() . '/yiipress-site-config-' . uniqid() . '.yaml';
        file_put_contents($filePath, "title: Test\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "languages" option must be a non-empty list.');

        (new SiteConfigParser())->parse($filePath);
    }
}

<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Content\Parser;

use YiiPress\Content\Parser\InvalidContentConfigException;
use YiiPress\Content\Parser\SiteConfigParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertStringContainsString;
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
        assertTrue($config->authorPages);
        assertSame('F j, Y', $config->dateFormat);
        assertSame(5, $config->entriesPerPage);
        assertSame('/:collection/:slug/', $config->permalink);
        assertSame(['tags', 'categories'], $config->taxonomies);
        assertSame(['github_url' => 'https://github.com/test'], $config->params);
        assertSame('local', $config->theme);
        assertSame('Solarized (dark)', $config->highlightTheme);
        assertTrue($config->assets->fingerprint);
        assertTrue($config->minify);
    }

    public function testParseMinifyConfigCanDisableOutputMinification(): void
    {
        $filePath = sys_get_temp_dir() . '/yiipress-site-config-' . uniqid() . '.yaml';
        file_put_contents($filePath, "title: Test\nlanguages: [en]\nminify: false\n");

        $parser = new SiteConfigParser();
        $config = $parser->parse($filePath);

        assertFalse($config->minify);

        unlink($filePath);
    }

    public function testParsesSiteDataFilesFromDataDirectory(): void
    {
        $contentDir = sys_get_temp_dir() . '/yiipress-site-data-' . uniqid();
        mkdir($contentDir . '/data', 0o755, true);
        file_put_contents($contentDir . '/config.yaml', "title: Test\nlanguages: [en]\n");
        file_put_contents($contentDir . '/data/company.YAML', "name: Acme\nlinks:\n  - /about/\n");
        file_put_contents($contentDir . '/data/metrics.yml', "posts: 12\n");
        file_put_contents($contentDir . '/data/active.yaml', "false\n");
        file_put_contents($contentDir . '/data/ignored.txt', "name: Ignored\n");

        try {
            $config = (new SiteConfigParser())->parse($contentDir . '/config.yaml');

            assertSame([
                'active' => false,
                'company' => ['name' => 'Acme', 'links' => ['/about/']],
                'metrics' => ['posts' => 12],
            ], $config->data);
        } finally {
            unlink($contentDir . '/data/ignored.txt');
            unlink($contentDir . '/data/active.yaml');
            unlink($contentDir . '/data/metrics.yml');
            unlink($contentDir . '/data/company.YAML');
            rmdir($contentDir . '/data');
            unlink($contentDir . '/config.yaml');
            rmdir($contentDir);
        }
    }

    public function testThrowsFriendlyExceptionForInvalidSiteDataYaml(): void
    {
        $contentDir = sys_get_temp_dir() . '/yiipress-site-data-' . uniqid();
        mkdir($contentDir . '/data', 0o755, true);
        file_put_contents($contentDir . '/config.yaml', "title: Test\nlanguages: [en]\n");
        $dataFile = $contentDir . '/data/broken.yaml';
        file_put_contents($dataFile, "name: [broken\n");

        try {
            (new SiteConfigParser())->parse($contentDir . '/config.yaml');
            $this->fail('Expected invalid content configuration exception.');
        } catch (InvalidContentConfigException $e) {
            assertSame($dataFile, $e->filePath());
            assertStringContainsString('Invalid YAML in site data file', $e->getMessage());
        } finally {
            unlink($dataFile);
            rmdir($contentDir . '/data');
            unlink($contentDir . '/config.yaml');
            rmdir($contentDir);
        }
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

    public function testParsesLastUpdatedConfig(): void
    {
        $filePath = sys_get_temp_dir() . '/yiipress-site-config-' . uniqid() . '.yaml';
        file_put_contents($filePath, "title: Test\nlanguages: [en]\nlast_updated: true\n");

        $parser = new SiteConfigParser();
        $config = $parser->parse($filePath);

        assertTrue($config->lastUpdated);

        unlink($filePath);
    }

    public function testParsesEditPageUrlConfig(): void
    {
        $filePath = sys_get_temp_dir() . '/yiipress-site-config-' . uniqid() . '.yaml';
        file_put_contents($filePath, "title: Test\nlanguages: [en]\nedit_page: https://example.com/edit/{path}\n");

        $parser = new SiteConfigParser();
        $config = $parser->parse($filePath);

        assertSame('https://example.com/edit/{path}', $config->editPageUrl);

        unlink($filePath);
    }

    public function testParsesReportIssueUrlConfig(): void
    {
        $filePath = sys_get_temp_dir() . '/yiipress-site-config-' . uniqid() . '.yaml';
        file_put_contents($filePath, "title: Test\nlanguages: [en]\nreport_issue: https://example.com/issues/new?title={title}\n");

        $parser = new SiteConfigParser();
        $config = $parser->parse($filePath);

        assertSame('https://example.com/issues/new?title={title}', $config->reportIssueUrl);

        unlink($filePath);
    }

    public function testDisablesAuthorPagesByDefault(): void
    {
        $filePath = sys_get_temp_dir() . '/yiipress-site-config-' . uniqid() . '.yaml';
        file_put_contents($filePath, "title: Test\nlanguages: [en]\n");

        $parser = new SiteConfigParser();
        $config = $parser->parse($filePath);

        assertFalse($config->authorPages);

        unlink($filePath);
    }

    public function testParsesAuthorPagesConfig(): void
    {
        $filePath = sys_get_temp_dir() . '/yiipress-site-config-' . uniqid() . '.yaml';
        file_put_contents($filePath, "title: Test\nlanguages: [en]\nauthor_pages: true\n");

        $parser = new SiteConfigParser();
        $config = $parser->parse($filePath);

        assertTrue($config->authorPages);

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

        try {
            (new SiteConfigParser())->parse($filePath);
            $this->fail('Expected invalid content configuration exception.');
        } catch (InvalidContentConfigException $e) {
            assertSame('Invalid content configuration', $e->getName());
            assertSame($filePath, $e->filePath());
            assertSame(
                'The "languages" option in site configuration must be a non-empty list of language codes.',
                $e->getMessage(),
            );
            assertStringContainsString('languages: [en]', (string) $e->getSolution());
        } finally {
            unlink($filePath);
        }
    }

    public function testThrowsFriendlyExceptionWhenConfigIsNotMapping(): void
    {
        $filePath = sys_get_temp_dir() . '/yiipress-site-config-' . uniqid() . '.yaml';
        file_put_contents($filePath, "- title\n");

        try {
            (new SiteConfigParser())->parse($filePath);
            $this->fail('Expected invalid content configuration exception.');
        } catch (InvalidContentConfigException $e) {
            assertSame('The site configuration file must contain YAML key-value pairs.', $e->getMessage());
            assertStringContainsString('title: My Site', (string) $e->getSolution());
        } finally {
            unlink($filePath);
        }
    }
}

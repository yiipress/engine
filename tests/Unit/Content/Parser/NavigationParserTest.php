<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Parser;

use App\Content\Parser\NavigationParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class NavigationParserTest extends TestCase
{
    public function testParseNavigationWithMenus(): void
    {
        $parser = new NavigationParser();
        $dataDir = dirname(__DIR__, 3) . '/Support/Data/content';

        $navigation = $parser->parse($dataDir . '/navigation.yaml');

        assertSame(['main', 'footer'], $navigation->menuNames());

        $main = $navigation->menu('main');
        assertCount(2, $main);
        assertSame('Blog', $main[0]->title);
        assertSame('/blog/', $main[0]->url);
        assertCount(0, $main[0]->children);

        assertSame('Docs', $main[1]->title);
        assertCount(1, $main[1]->children);
        assertSame('Getting Started', $main[1]->children[0]->title);
        assertSame('/docs/getting-started/', $main[1]->children[0]->url);
    }

    public function testNonExistentMenuReturnsEmptyArray(): void
    {
        $parser = new NavigationParser();
        $dataDir = dirname(__DIR__, 3) . '/Support/Data/content';

        $navigation = $parser->parse($dataDir . '/navigation.yaml');

        assertCount(0, $navigation->menu('nonexistent'));
    }

    public function testParseLocalizedMenuTitles(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-nav-');
        file_put_contents($file, <<<'YAML'
main:
  - title:
      en: About
      ru: О сайте
    url: /about/
YAML);

        try {
            $parser = new NavigationParser();
            $navigation = $parser->parse($file);
            $main = $navigation->menu('main');

            assertSame('About', $main[0]->title);
            assertSame(['en' => 'About', 'ru' => 'О сайте'], $main[0]->titles);
            assertSame('О сайте', $main[0]->resolveTitle('ru', 'en'));
        } finally {
            unlink($file);
        }
    }
}

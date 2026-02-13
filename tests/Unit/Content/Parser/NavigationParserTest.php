<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Parser;

use App\Content\Parser\NavigationParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;

final class NavigationParserTest extends TestCase
{
    public function testParseNavigationWithMenus(): void
    {
        $parser = new NavigationParser();
        $dataDir = dirname(__DIR__, 3) . '/Support/Data/content';

        $navigation = $parser->parse($dataDir . '/navigation.yaml');

        assertSame(['main', 'footer'], $navigation->menuNames());

        $main = $navigation->menu('main');
        assertCount(3, $main);
        assertSame('Home', $main[0]->title);
        assertSame('/', $main[0]->url);
        assertCount(0, $main[0]->children);

        assertSame('Docs', $main[2]->title);
        assertCount(1, $main[2]->children);
        assertSame('Getting Started', $main[2]->children[0]->title);
        assertSame('/docs/getting-started/', $main[2]->children[0]->url);
    }

    public function testNonExistentMenuReturnsEmptyArray(): void
    {
        $parser = new NavigationParser();
        $dataDir = dirname(__DIR__, 3) . '/Support/Data/content';

        $navigation = $parser->parse($dataDir . '/navigation.yaml');

        assertCount(0, $navigation->menu('nonexistent'));
    }
}

<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\PortablePath;

use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

final class PortablePathTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function portablePathDataProvider(): iterable
    {
        yield 'file' => ['emoji.php'];
        yield 'nested' => ['src/processors/emoji.php'];
        yield 'unicode' => ['screenshots/главная.png'];
        yield 'dot inside name' => ['a.b/c.d.png'];
        yield 'reserved word inside name' => ['console.php'];
        yield 'longest segment' => [str_repeat('a', 100)];
    }

    #[DataProvider('portablePathDataProvider')]
    public function testAcceptsPortablePath(string $path): void
    {
        assertNull(PortablePath::problem($path));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function problemDataProvider(): iterable
    {
        yield 'empty' => ['', 'must not be empty'];
        yield 'absolute' => ['/etc/passwd', 'must be relative and must not start with "/"'];
        yield 'backslash' => ['a\\b.php', 'must use "/" as a separator, not "\\"'];
        yield 'drive letter' => ['C:/a.php', 'must not contain any of <>:"|?*'];
        yield 'empty segment' => ['a//b.php', 'must not contain empty, "." or ".." segments'];
        yield 'trailing slash' => ['a/', 'must not contain empty, "." or ".." segments'];
        yield 'dot segment' => ['./a.php', 'must not contain empty, "." or ".." segments'];
        yield 'parent segment' => ['a/../../b.php', 'must not contain empty, "." or ".." segments'];
        yield 'too long' => [str_repeat('a/', 127) . 'ab', 'must not be longer than 255 bytes'];
        yield 'long segment' => [str_repeat('a', 101), 'must not have a segment longer than 100 bytes'];
        yield 'invalid utf-8' => ["a\xFF.php", 'must be valid UTF-8'];
        yield 'control character' => ["a\tb.php", 'must not contain control characters'];
        yield 'question mark' => ['a?.php', 'must not contain any of <>:"|?*'];
        yield 'pipe' => ['a|b.php', 'must not contain any of <>:"|?*'];
        yield 'reserved name' => ['aux', 'must not use the reserved Windows name "aux"'];
        yield 'reserved name with extension' => ['src/CON.php', 'must not use the reserved Windows name "CON.php"'];
        yield 'reserved name with two extensions' => ['nul.tar.gz', 'must not use the reserved Windows name "nul.tar.gz"'];
        yield 'trailing dot' => ['a./b.php', 'must not have a segment that ends in a dot or a space'];
        yield 'trailing space' => ['a /b.php', 'must not have a segment that ends in a dot or a space'];
    }

    #[DataProvider('problemDataProvider')]
    public function testDescribesProblem(string $path, string $expected): void
    {
        assertSame($expected, PortablePath::problem($path));
    }
}

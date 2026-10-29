<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Archive;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\Archive\InstallLimits;
use YiiPress\Extension\Archive\TarGzReader;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Tests\Support\TarGzBuilder;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function gzencode;
use function is_dir;
use function is_file;
use function mkdir;
use function str_repeat;
use function substr;
use function sys_get_temp_dir;
use function uniqid;

final class TarGzReaderTest extends TestCase
{
    private const string COMMIT = 'acf0346e709f2934037914577a98a6ee62a28000';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/yiipress-targz-test-' . uniqid();
        mkdir($this->dir, 0o755, true);
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->dir);
    }

    public function testExtractsGitArchiveStrippingEnclosingDirectory(): void
    {
        $archive = self::valid()
            ->file('theme-wonder-1.0.0/screenshots/home.png', 'png')
            ->writeTo($this->dir . '/a.tar.gz');

        $result = new TarGzReader()->extract($archive, $this->dir . '/root', new InstallLimits());

        self::assertSame(self::COMMIT, $result->commit);
        self::assertSame($this->dir . '/root', $result->root);
        self::assertSame("id: yiipress/wonder\n", file_get_contents($this->dir . '/root/extension.yaml'));
        self::assertSame('<?php echo 1;', file_get_contents($this->dir . '/root/package/layout.php'));
        self::assertSame('png', file_get_contents($this->dir . '/root/screenshots/home.png'));
        self::assertTrue(is_dir($this->dir . '/root/package/assets'));
    }

    public function testExtractsArchiveMadeByGitArchive(): void
    {
        $result = new TarGzReader()->extract(
            dirname(__DIR__, 3) . '/Support/Data/extension/wonder-v1.0.0.tar.gz',
            $this->dir . '/root',
            new InstallLimits(),
        );

        self::assertSame('a42fc8f6623de13e9bb4d9db0757d5730062948a', $result->commit);
        self::assertSame("body{}\n", file_get_contents($result->root . '/package/assets/style.css'));
        self::assertTrue(is_file($result->root . '/extension.yaml'));
    }

    public function testHonoursPaxPathAndUstarPrefix(): void
    {
        $long = 'theme-wonder-1.0.0/package/' . str_repeat('d', 90) . '/' . str_repeat('f', 60) . '.css';
        $archive = self::valid()
            ->paxHeader(['path' => $long])
            ->file('ignored-name', 'a{}')
            ->entry('b.css', '0', 'b{}', prefix: 'theme-wonder-1.0.0/package')
            ->writeTo($this->dir . '/a.tar.gz');

        $root = new TarGzReader()->extract($archive, $this->dir . '/root', new InstallLimits())->root;

        self::assertSame('a{}', file_get_contents($root . '/package/' . str_repeat('d', 90) . '/' . str_repeat('f', 60) . '.css'));
        self::assertSame('b{}', file_get_contents($root . '/package/b.css'));
        self::assertFalse(is_file($root . '/ignored-name'));
    }

    public function testLargeFileIsCopiedInChunks(): void
    {
        $contents = str_repeat('0123456789abcdef', 70_000);
        $archive = self::valid()->file('theme-wonder-1.0.0/package/big.css', $contents)->writeTo($this->dir . '/a.tar.gz');

        $root = new TarGzReader()->extract($archive, $this->dir . '/root', new InstallLimits())->root;

        self::assertSame($contents, file_get_contents($root . '/package/big.css'));
    }

    /**
     * @param Closure(string): void $prepare
     */
    #[DataProvider('brokenArchives')]
    public function testRejectsBrokenArchive(Closure $prepare, string $expectedCode, InstallLimits $limits = new InstallLimits()): void
    {
        $file = $this->dir . '/a.tar.gz';
        $prepare($file);

        try {
            new TarGzReader()->extract($file, $this->dir . '/root', $limits);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame($expectedCode, $exception->errorCode, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{0: Closure(string): void, 1: string, 2?: InstallLimits}>
     */
    public static function brokenArchives(): iterable
    {
        yield 'not gzip' => [
            static fn(string $f) => file_put_contents($f, self::valid()->tar()),
            'archive_invalid',
        ];
        yield 'not a tar' => [
            static fn(string $f) => file_put_contents($f, gzencode(str_repeat('x', 2048))),
            'archive_invalid',
        ];
        yield 'bad header checksum' => [
            static fn(string $f) => self::valid()
                ->entry('theme-wonder-1.0.0/package/x.css', '0', 'x', corruptChecksum: true)
                ->writeTo($f),
            'archive_invalid',
        ];
        yield 'no end blocks' => [
            static fn(string $f) => self::valid()->writeTo($f, endBlocks: false),
            'archive_invalid',
        ];
        yield 'data shorter than declared size' => [
            static fn(string $f) => file_put_contents($f, gzencode(
                self::valid()->tar(false) . substr(
                    new TarGzBuilder()->entry('theme-wonder-1.0.0/package/x.css', '0', 'abc', declaredSize: 5000)->tar(false),
                    0,
                    1024,
                ),
            )),
            'archive_invalid',
        ];
        yield 'symbolic link' => [
            static fn(string $f) => self::valid()->symlink('theme-wonder-1.0.0/package/l', 'layout.php')->writeTo($f),
            'archive_link',
        ];
        yield 'hard link' => [
            static fn(string $f) => self::valid()
                ->entry('theme-wonder-1.0.0/package/h', '1', '', linkName: 'theme-wonder-1.0.0/package/layout.php')
                ->writeTo($f),
            'archive_link',
        ];
        yield 'GNU long name' => [
            static fn(string $f) => self::valid()->entry('././@LongLink', 'L', 'theme-wonder-1.0.0/x')->writeTo($f),
            'archive_entry_type',
        ];
        yield 'character device' => [
            static fn(string $f) => self::valid()->entry('theme-wonder-1.0.0/package/dev', '3', '')->writeTo($f),
            'archive_entry_type',
        ];
        yield 'second top-level directory' => [
            static fn(string $f) => self::valid()->file('other/extension.yaml', 'x')->writeTo($f),
            'archive_layout',
        ];
        yield 'file at top level' => [
            static fn(string $f) => self::valid()->file('README.md', 'x')->writeTo($f),
            'archive_layout',
        ];
        yield 'no package directory' => [
            static fn(string $f) => TarGzBuilder::gitArchive('theme-wonder-1.0.0')
                ->file('theme-wonder-1.0.0/extension.yaml', 'id: x')
                ->writeTo($f),
            'archive_layout',
        ];
        yield 'no extension.yaml' => [
            static fn(string $f) => TarGzBuilder::gitArchive('theme-wonder-1.0.0')
                ->file('theme-wonder-1.0.0/package/layout.php', 'x')
                ->writeTo($f),
            'archive_layout',
        ];
        yield 'extension.yaml is a directory' => [
            static fn(string $f) => TarGzBuilder::gitArchive('theme-wonder-1.0.0')
                ->directory('theme-wonder-1.0.0/extension.yaml/')
                ->file('theme-wonder-1.0.0/package/layout.php', 'x')
                ->writeTo($f),
            'archive_layout',
        ];
        yield 'dot-dot segment' => [
            static fn(string $f) => self::valid()->file('theme-wonder-1.0.0/package/../../evil.php', 'x')->writeTo($f),
            'archive_path_invalid',
        ];
        yield 'reserved Windows name' => [
            static fn(string $f) => self::valid()->file('theme-wonder-1.0.0/package/CON.txt', 'x')->writeTo($f),
            'archive_path_invalid',
        ];
        yield 'paths equal ignoring case' => [
            static fn(string $f) => self::valid()->file('theme-wonder-1.0.0/package/Layout.php', 'x')->writeTo($f),
            'archive_path_collision',
        ];
        yield 'same file twice' => [
            static fn(string $f) => self::valid()->file('theme-wonder-1.0.0/package/layout.php', 'x')->writeTo($f),
            'archive_path_collision',
        ];
        yield 'file over directory' => [
            static fn(string $f) => self::valid()->file('theme-wonder-1.0.0/package/assets', 'x')->writeTo($f),
            'archive_path_collision',
        ];
        yield 'directory under file' => [
            static fn(string $f) => self::valid()->file('theme-wonder-1.0.0/package/layout.php/x.css', 'x')->writeTo($f),
            'archive_path_collision',
        ];
        yield 'too many entries' => [
            static fn(string $f) => self::valid()->writeTo($f),
            'archive_too_many_entries',
            new InstallLimits(entries: 3),
        ];
        yield 'file too large' => [
            static fn(string $f) => self::valid()->file('theme-wonder-1.0.0/package/big.css', str_repeat('x', 101))->writeTo($f),
            'archive_file_too_large',
            new InstallLimits(fileBytes: 100),
        ];
        yield 'extracted size too large' => [
            static fn(string $f) => self::valid()
                ->file('theme-wonder-1.0.0/package/a.css', str_repeat('x', 30))
                ->file('theme-wonder-1.0.0/package/b.css', str_repeat('x', 30))
                ->writeTo($f),
            'archive_too_large',
            new InstallLimits(extractedBytes: 80),
        ];
        yield 'no global header' => [
            static fn(string $f) => new TarGzBuilder()
                ->directory('theme-wonder-1.0.0/')
                ->file('theme-wonder-1.0.0/extension.yaml', 'x')
                ->directory('theme-wonder-1.0.0/package/')
                ->writeTo($f),
            'archive_commit_missing',
        ];
        yield 'comment is not a commit' => [
            static fn(string $f) => TarGzBuilder::gitArchive('theme-wonder-1.0.0', 'hello')
                ->file('theme-wonder-1.0.0/extension.yaml', 'x')
                ->directory('theme-wonder-1.0.0/package/')
                ->writeTo($f),
            'archive_commit_missing',
        ];
        yield 'malformed pax record' => [
            static fn(string $f) => self::valid()->entry('PaxHeader', 'x', "99 path=x\n")->file('theme-wonder-1.0.0/a', 'x')->writeTo($f),
            'archive_invalid',
        ];
    }

    public function testRefusesExistingTargetDirectory(): void
    {
        $archive = self::valid()->writeTo($this->dir . '/a.tar.gz');
        mkdir($this->dir . '/root');

        $this->expectException(InstallException::class);
        $this->expectExceptionMessage('already exists');

        new TarGzReader()->extract($archive, $this->dir . '/root', new InstallLimits());
    }

    private static function valid(): TarGzBuilder
    {
        return TarGzBuilder::gitArchive('theme-wonder-1.0.0', self::COMMIT)
            ->file('theme-wonder-1.0.0/extension.yaml', "id: yiipress/wonder\n")
            ->directory('theme-wonder-1.0.0/package/')
            ->directory('theme-wonder-1.0.0/package/assets/')
            ->file('theme-wonder-1.0.0/package/layout.php', '<?php echo 1;');
    }
}

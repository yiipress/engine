<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Lock;

use Closure;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockFile;

use function json_decode;
use function hash;
use function json_encode;
use function str_repeat;
use function sys_get_temp_dir;
use function uniqid;

final class LockFileTest extends TestCase
{
    private const string SPEC_EXAMPLE = <<<'JSON'
        {
            "extensions": {
                "yiipress/wonder": {
                    "archive": {
                        "kind": "github-tarball",
                        "sha256": "afe2cba716f10651c7286f7a794240808ce231e0f7353184af4e458e65c116a6",
                        "size": 48213,
                        "url": "https://codeload.github.com/yiipress/theme-wonder/tar.gz/refs/tags/v1.0.0"
                    },
                    "commit": "acf0346e709f2934037914577a98a6ee62a28000",
                    "destination": "themes/wonder",
                    "files": {
                        "assets/style.css": "9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08",
                        "layout.php": "60303ae22b998861bce3b28f33eec1be758a213c86c93c076dbe9f558c11c752"
                    },
                    "manifest_sha256": "2c26b46b68ffc68ff99b453c1d30413413422d706483bfa0f98a5e886266e7ae",
                    "name": "wonder",
                    "source": "https://github.com/yiipress/theme-wonder",
                    "tag": "v1.0.0",
                    "type": "theme",
                    "version": "1.0.0"
                }
            },
            "lock": 1
        }

        JSON;

    public function testReadsAndWritesSpecExampleByteForByte(): void
    {
        $lock = LockFile::fromJson(self::SPEC_EXAMPLE);

        self::assertSame(self::SPEC_EXAMPLE, $lock->toJson());
        self::assertSame(['themes/wonder'], $lock->destinations());
        self::assertSame('wonder', $lock->get(self::id('yiipress/wonder'))?->name);
    }

    public function testSerialisationDoesNotDependOnInsertionOrder(): void
    {
        $a = LockFile::empty()
            ->withExtension(self::id('b/two'), self::extension('two', ['z.css' => self::hash('z'), 'a.php' => self::hash('a')]))
            ->withExtension(self::id('a/one'), self::extension('one'));
        $b = LockFile::empty()
            ->withExtension(self::id('a/one'), self::extension('one'))
            ->withExtension(self::id('b/two'), self::extension('two', ['a.php' => self::hash('a'), 'z.css' => self::hash('z')]));

        self::assertSame($a->toJson(), $b->toJson());
        self::assertSame($a->toJson(), LockFile::fromJson($a->toJson())->toJson());
    }

    public function testEmptyLockSerialisesObjects(): void
    {
        self::assertSame("{\n    \"extensions\": {},\n    \"lock\": 1\n}\n", LockFile::empty()->toJson());
    }

    public function testMissingFileIsEmptyLock(): void
    {
        $lock = LockFile::read(sys_get_temp_dir() . '/yiipress-no-lock-' . uniqid() . '/yiipress.lock');

        self::assertSame([], $lock->extensions());
    }

    /**
     * @param Closure(array<string, mixed>): array<string, mixed> $change
     */
    #[DataProvider('invalidLocks')]
    public function testRejectsInvalidLock(Closure $change, string $code, string $field): void
    {
        /** @var array<string, mixed> $data */
        $data = json_decode(self::SPEC_EXAMPLE, true);

        try {
            LockFile::fromJson((string) json_encode($change($data)));
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame($code, $exception->errorCode);
            self::assertStringContainsString($field, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{Closure, string, string}>
     */
    public static function invalidLocks(): iterable
    {
        $entry = static fn(string $key, mixed $value): Closure => static function (array $d) use ($key, $value): array {
            $d['extensions']['yiipress/wonder'][$key] = $value;
            return $d;
        };

        yield 'newer format' => [static fn(array $d): array => ['lock' => 2] + $d, 'lock_unsupported', 'format 2'];
        yield 'no format' => [static fn(array $d): array => ['extensions' => []], 'lock_invalid', '"lock"'];
        yield 'unknown top key' => [static fn(array $d): array => $d + ['x' => 1], 'lock_invalid', '"x"'];
        yield 'bad id' => [
            static fn(array $d): array => ['lock' => 1, 'extensions' => ['Wonder' => []]],
            'lock_invalid',
            'extensions.Wonder',
        ];
        yield 'unknown entry key' => [$entry('extra', 1), 'lock_invalid', 'extra'];
        yield 'bad type' => [$entry('type', 'skin'), 'lock_invalid', 'extensions.yiipress/wonder.type'];
        yield 'bad source' => [$entry('source', 'https://github.com/yiipress/theme-wonder.git'), 'lock_invalid', 'source'];
        yield 'bad commit' => [$entry('commit', 'abc'), 'lock_invalid', 'commit'];
        yield 'escaping destination' => [$entry('destination', '../themes/wonder'), 'lock_invalid', 'destination'];
        yield 'escaping file' => [
            $entry('files', ['../x.php' => '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08']),
            'lock_invalid',
            'files',
        ];
        yield 'bad file hash' => [$entry('files', ['x.php' => 'abc']), 'lock_invalid', 'files.x.php'];
        yield 'archive size' => [
            $entry('archive', ['kind' => 'github-tarball', 'sha256' => str_repeat('a', 64), 'size' => '1', 'url' => 'https://x/y']),
            'lock_invalid',
            'archive.size',
        ];
        yield 'archive kind' => [
            $entry('archive', ['kind' => 'zip', 'sha256' => str_repeat('a', 64), 'size' => 1, 'url' => 'https://x/y']),
            'lock_invalid',
            'archive.kind',
        ];
        yield 'not JSON' => [static fn(array $d): array => [], 'lock_invalid', 'lock'];
    }

    public function testRejectsBrokenJson(): void
    {
        $this->expectExceptionObject(new InstallException('lock_invalid', 'yiipress.lock is not valid JSON: Syntax error.'));

        LockFile::fromJson('{');
    }

    private static function id(string $id): ExtensionId
    {
        return ExtensionId::tryFromString($id) ?? throw new LogicException('Bad ID.');
    }

    /**
     * @param array<string, string> $files
     */
    private static function extension(string $name, array $files = []): LockedExtension
    {
        return new LockedExtension(
            type: ExtensionType::Theme,
            source: "https://github.com/x/theme-$name",
            version: '1.0.0',
            tag: 'v1.0.0',
            commit: str_repeat('a', 40),
            archiveKind: LockedExtension::ARCHIVE_GITHUB_TARBALL,
            archiveUrl: "https://codeload.github.com/x/theme-$name/tar.gz/refs/tags/v1.0.0",
            archiveSha256: self::hash('archive'),
            archiveSize: 10,
            destination: "themes/$name",
            name: $name,
            manifestSha256: self::hash('manifest'),
            files: $files,
        );
    }

    private static function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}

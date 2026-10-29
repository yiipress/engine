<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Transaction;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Transaction\Recovery;
use YiiPress\Extension\Transaction\Transaction;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function json_encode;
use function mkdir;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;

use const JSON_THROW_ON_ERROR;

/**
 * Recovery touches only the files an installation may change, and only inside the project.
 */
final class JournalPathsTest extends TestCase
{
    private const string TX = '0123456789abcdef';

    private string $root;
    private string $outside;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/yiipress-journal-paths-test-' . uniqid();
        $this->root = $base . '/project';
        $this->outside = $base . '/outside';
        mkdir($this->root . '/.yiipress/backup/' . self::TX, 0o755, true);
        mkdir($this->outside);
        file_put_contents($this->outside . '/config.yaml', 'precious');
        file_put_contents($this->outside . '/yiipress.lock', 'precious');
        file_put_contents($this->root . '/.yiipress/backup/' . self::TX . '/1', 'attacker');
        symlink($this->outside, $this->root . '/link');
        mkdir($this->root . '/themes/wonder', 0o755, true);
        symlink($this->outside, $this->root . '/themes/wonder/link');
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove(dirname($this->root));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unsafeSteps(): iterable
    {
        $backup = '.yiipress/backup/' . self::TX . '/1';
        $staged = '.yiipress/staging/' . self::TX . '/files/0';
        yield 'overwrite through symlinked parent' => [
            ['kind' => 'replace_file', 'path' => 'link/config.yaml', 'staged' => $staged, 'backup' => $backup],
        ];
        yield 'delete through symlinked parent' => [
            ['kind' => 'replace_file', 'path' => 'link/config.yaml', 'staged' => $staged, 'backup' => null],
        ];
        yield 'file an installation never changes' => [
            ['kind' => 'replace_file', 'path' => 'index.php', 'staged' => $staged, 'backup' => null],
        ];
        yield 'backup outside the transaction' => [
            ['kind' => 'replace_file', 'path' => 'yiipress.lock', 'staged' => $staged, 'backup' => 'link/yiipress.lock'],
        ];
        yield 'directory outside themes' => [
            ['kind' => 'move_dir', 'from' => '.yiipress/staging/' . self::TX . '/root/package', 'to' => 'link', 'hashes' => []],
        ];
        yield 'added file outside themes' => [
            ['kind' => 'add_file', 'from' => $staged, 'to' => 'index.php', 'hash' => ''],
        ];
        yield 'added file through symlinked parent' => [
            ['kind' => 'add_file', 'from' => $staged, 'to' => 'themes/wonder/link/config.yaml', 'hash' => hash('sha256', 'precious')],
        ];
        yield 'added file staged outside the transaction' => [
            ['kind' => 'add_file', 'from' => 'link/config.yaml', 'to' => 'themes/wonder/config.yaml', 'hash' => ''],
        ];
    }

    /**
     * @param array<string, mixed> $step
     */
    #[DataProvider('unsafeSteps')]
    public function testRecoveryRefusesUnsafeStepAndChangesNothing(array $step): void
    {
        file_put_contents($this->root . '/.yiipress/journal.json', json_encode([
            'journal' => 1,
            'tx' => self::TX,
            'operation' => 'install',
            'state' => 'prepared',
            'steps' => [$step],
        ], JSON_THROW_ON_ERROR));

        try {
            new Recovery()->recover($this->root);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('recovery_failed', $exception->errorCode, $exception->getMessage());
        }

        self::assertSame('precious', file_get_contents($this->outside . '/config.yaml'));
        self::assertSame('precious', file_get_contents($this->outside . '/yiipress.lock'));
        self::assertFileExists($this->root . '/.yiipress/journal.json');
    }

    public function testTransactionRefusesFileAnInstallationNeverChanges(): void
    {
        $transaction = Transaction::begin($this->root);

        $this->expectException(InstallException::class);

        $transaction->replaceFile('index.php', '<?php');
    }

    public function testTransactionRefusesPathWithDotSegment(): void
    {
        $transaction = Transaction::begin($this->root);

        $this->expectException(InstallException::class);

        $transaction->replaceFile('./content/config.yaml', 'theme: wonder');
    }

    public function testTransactionRefusesTargetOutsideProject(): void
    {
        $transaction = Transaction::begin($this->root);
        $transaction->replaceFile('link/config.yaml', 'theme: wonder');

        try {
            $transaction->commit();
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('filesystem_failed', $exception->errorCode, $exception->getMessage());
        }
        self::assertSame('precious', file_get_contents($this->outside . '/config.yaml'));
    }
}

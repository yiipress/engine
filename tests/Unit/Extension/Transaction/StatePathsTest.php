<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Transaction;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Transaction\MutationLock;
use YiiPress\Extension\Transaction\Recovery;
use YiiPress\Extension\Transaction\StatePaths;
use YiiPress\Extension\Transaction\Transaction;

use function dirname;
use function file_put_contents;
use function mkdir;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;

final class StatePathsTest extends TestCase
{
    private string $base;
    private string $root;
    private string $outside;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/yiipress-state-paths-test-' . uniqid();
        $this->root = $this->base . '/project';
        $this->outside = $this->base . '/outside';
        mkdir($this->outside . '/precious', 0o755, true);
        file_put_contents($this->outside . '/precious/keep.txt', 'keep');
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->base);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function stateDirectories(): iterable
    {
        yield 'state directory' => ['.yiipress'];
        yield 'staging' => ['.yiipress/staging'];
        yield 'backup' => ['.yiipress/backup'];
    }

    #[DataProvider('stateDirectories')]
    public function testRecoveryRefusesLinkedStateDirectory(string $directory): void
    {
        $this->link($directory);

        $this->assertRefused(fn() => new Recovery()->recover($this->root), $directory);
        self::assertFileExists($this->outside . '/precious/keep.txt');
    }

    #[DataProvider('stateDirectories')]
    public function testMutationLockRefusesLinkedStateDirectory(string $directory): void
    {
        $this->link($directory);

        $this->assertRefused(fn() => MutationLock::acquire($this->root), $directory);
        self::assertFileDoesNotExist($this->outside . '/mutation.lock');
    }

    #[DataProvider('stateDirectories')]
    public function testTransactionRefusesLinkedStateDirectory(string $directory): void
    {
        $this->link($directory);

        $this->assertRefused(fn() => Transaction::begin($this->root), $directory);
        self::assertFileDoesNotExist($this->outside . '/staging');
    }

    public function testStateDirectoryUnderLinkedParentOutsideRootIsRefused(): void
    {
        mkdir($this->outside . '/state/staging', 0o755, true);
        symlink($this->outside . '/state', $this->root . '/.yiipress');

        $this->assertRefused(fn() => new StatePaths($this->root)->assertSafe(), '.yiipress');
    }

    public function testRegularStateDirectoriesAreAccepted(): void
    {
        mkdir($this->root . '/.yiipress/staging', 0o755, true);
        mkdir($this->root . '/.yiipress/backup', 0o755, true);

        new StatePaths($this->root)->assertSafe();

        self::assertNull(new Recovery()->recover($this->root));
    }

    private function link(string $directory): void
    {
        if (dirname($directory) !== '.') {
            mkdir($this->root . '/' . dirname($directory), 0o755, true);
        }
        symlink($this->outside, $this->root . '/' . $directory);
    }

    private function assertRefused(callable $action, string $directory): void
    {
        try {
            $action();
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('filesystem_failed', $exception->errorCode);
            self::assertStringContainsString($directory, $exception->getMessage());
        }
    }
}

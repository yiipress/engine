<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Transaction;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\DirectoryHashes;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Transaction\Journal;
use YiiPress\Extension\Transaction\MutationLock;
use YiiPress\Extension\Transaction\Recovery;
use YiiPress\Extension\Transaction\StatePaths;
use YiiPress\Extension\Transaction\Transaction;

use function chmod;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function hash;
use function is_dir;
use function is_link;
use function json_encode;
use function mkdir;
use function rename;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;

use const GLOB_BRACE;

final class TransactionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-transaction-test-' . uniqid();
        mkdir($this->root . '/content', 0o755, true);
        file_put_contents($this->root . '/content/config.yaml', "title: Blog\n");
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testCommitMovesDirectoryAndReplacesFiles(): void
    {
        $tx = Transaction::begin($this->root);
        $hashes = $this->stagePackage($tx);
        $tx->moveDirectory($tx->stagingPath('package'), 'themes/wonder', $hashes);
        $tx->replaceFile('yiipress.lock', "{}\n");
        $tx->replaceFile('content/config.yaml', "title: Blog\ntheme: wonder\n");

        $tx->commit();

        self::assertSame('<?php', file_get_contents($this->root . '/themes/wonder/entry.php'));
        self::assertSame("{}\n", file_get_contents($this->root . '/yiipress.lock'));
        self::assertSame("title: Blog\ntheme: wonder\n", file_get_contents($this->root . '/content/config.yaml'));
        self::assertFalse(file_exists($this->root . '/.yiipress/journal.json'));
        self::assertFalse(is_dir($this->root . '/.yiipress/staging/' . $tx->tx));
        self::assertFalse(is_dir($this->root . '/.yiipress/backup/' . $tx->tx));
    }

    public function testFailedStepUndoesAppliedSteps(): void
    {
        file_put_contents($this->root . '/blocker', 'a file');
        $tx = Transaction::begin($this->root);
        $hashes = $this->stagePackage($tx);
        $tx->moveDirectory($tx->stagingPath('package'), 'themes/wonder', $hashes);
        $tx->replaceFile('content/config.yaml', "theme: wonder\n");
        $tx->replaceFile('blocker/config.yaml', 'x');

        try {
            $tx->commit();
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('filesystem_failed', $exception->errorCode);
        }

        self::assertFalse(file_exists($this->root . '/themes/wonder'));
        self::assertSame("title: Blog\n", file_get_contents($this->root . '/content/config.yaml'));
        self::assertFalse(file_exists($this->root . '/.yiipress/journal.json'));
    }

    public function testDestinationThatAppearsIsNotTouched(): void
    {
        $tx = Transaction::begin($this->root);
        $hashes = $this->stagePackage($tx);
        $tx->moveDirectory($tx->stagingPath('package'), 'themes/wonder', $hashes);
        mkdir($this->root . '/themes/wonder', 0o755, true);
        file_put_contents($this->root . '/themes/wonder/mine.php', 'mine');

        try {
            $tx->commit();
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('destination_exists', $exception->errorCode);
        }

        self::assertSame('mine', file_get_contents($this->root . '/themes/wonder/mine.php'));
    }

    public function testCommitAddsMissingFileToExistingDirectory(): void
    {
        mkdir($this->root . '/themes/wonder', 0o755, true);
        file_put_contents($this->root . '/themes/wonder/entry.php', '<?php');
        $tx = Transaction::begin($this->root);
        $this->stagePackage($tx);

        $tx->addFile(
            $tx->stagingPath('package/assets/style.css'),
            'themes/wonder/assets/style.css',
            hash('sha256', 'body{}'),
        );
        $tx->commit();

        self::assertSame('body{}', file_get_contents($this->root . '/themes/wonder/assets/style.css'));
        self::assertSame('<?php', file_get_contents($this->root . '/themes/wonder/entry.php'));
        self::assertFalse(Recovery::pending($this->root));
    }

    public function testAddedFileThatAppearsIsNotTouched(): void
    {
        mkdir($this->root . '/themes/wonder', 0o755, true);
        $tx = Transaction::begin($this->root);
        $this->stagePackage($tx);
        $tx->addFile($tx->stagingPath('package/entry.php'), 'themes/wonder/entry.php', hash('sha256', '<?php'));
        file_put_contents($this->root . '/themes/wonder/entry.php', 'mine');

        try {
            $tx->commit();
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('destination_exists', $exception->errorCode);
        }

        self::assertSame('mine', file_get_contents($this->root . '/themes/wonder/entry.php'));
    }

    public function testCommitReplacesRetiredDirectory(): void
    {
        $this->installOldPackage();
        $tx = Transaction::begin($this->root);
        $hashes = $this->stagePackage($tx);
        $tx->retireDirectory('themes/wonder');
        $tx->moveDirectory($tx->stagingPath('package'), 'themes/wonder', $hashes);
        $tx->replaceFile('yiipress.lock', "{\"new\": true}\n");

        $tx->commit();

        self::assertSame('<?php', file_get_contents($this->root . '/themes/wonder/entry.php'));
        self::assertFileDoesNotExist($this->root . '/themes/wonder/old.php');
        self::assertSame("{\"new\": true}\n", file_get_contents($this->root . '/yiipress.lock'));
        self::assertFalse(is_dir($this->root . '/.yiipress/backup/' . $tx->tx));
        self::assertFalse(Recovery::pending($this->root));
    }

    public function testCommitDeletesRetiredDirectory(): void
    {
        $this->installOldPackage();
        $tx = Transaction::begin($this->root);
        $tx->retireDirectory('themes/wonder');
        $tx->replaceFile('yiipress.lock', "{}\n");

        $tx->commit();

        self::assertFalse(file_exists($this->root . '/themes/wonder'));
        self::assertTrue(is_dir($this->root . '/themes'));
        self::assertSame("{}\n", file_get_contents($this->root . '/yiipress.lock'));
        self::assertFalse(is_dir($this->root . '/.yiipress/backup/' . $tx->tx));
    }

    public function testFailedStepPutsRetiredDirectoryBack(): void
    {
        $this->installOldPackage();
        file_put_contents($this->root . '/blocker', 'a file');
        $tx = Transaction::begin($this->root);
        $hashes = $this->stagePackage($tx);
        $tx->retireDirectory('themes/wonder');
        $tx->moveDirectory($tx->stagingPath('package'), 'themes/wonder', $hashes);
        $tx->replaceFile('yiipress.lock', "{}\n");
        $tx->replaceFile('blocker/config.yaml', 'x');

        try {
            $tx->commit();
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('filesystem_failed', $exception->errorCode);
        }

        $this->assertOldPackageInPlace();
        self::assertFalse(Recovery::pending($this->root));
    }

    public function testRetiringMissingDirectoryChangesNothing(): void
    {
        file_put_contents($this->root . '/yiipress.lock', "{\"old\": true}\n");
        $tx = Transaction::begin($this->root);
        $tx->retireDirectory('themes/wonder');
        $tx->replaceFile('yiipress.lock', "{}\n");

        try {
            $tx->commit();
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('filesystem_failed', $exception->errorCode);
        }

        self::assertSame("{\"old\": true}\n", file_get_contents($this->root . '/yiipress.lock'));
        self::assertFalse(Recovery::pending($this->root));
    }

    public function testRetiringLinkIsRefused(): void
    {
        mkdir($this->root . '/elsewhere');
        file_put_contents($this->root . '/elsewhere/mine.php', 'mine');
        mkdir($this->root . '/themes');
        symlink($this->root . '/elsewhere', $this->root . '/themes/wonder');
        $tx = Transaction::begin($this->root);
        $tx->retireDirectory('themes/wonder');

        try {
            $tx->commit();
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('filesystem_failed', $exception->errorCode);
        }

        self::assertTrue(is_link($this->root . '/themes/wonder'));
        self::assertSame('mine', file_get_contents($this->root . '/elsewhere/mine.php'));
    }

    public function testRetiringOtherThanPackageDirectoryIsRefused(): void
    {
        $tx = Transaction::begin($this->root);

        $this->expectExceptionObject(new InstallException(
            'filesystem_failed',
            'An extension operation cannot change "content".',
        ));

        $tx->retireDirectory('content');
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function killedUpdateProvider(): iterable
    {
        yield 'killed before retiring' => [false, false];
        yield 'killed after retiring' => [true, false];
        yield 'killed after the replacement was in place' => [true, true];
    }

    #[DataProvider('killedUpdateProvider')]
    public function testRecoveryPutsBackPackageOfKilledUpdate(bool $retired, bool $replaced): void
    {
        $this->simulateKilledUpdate($retired, $replaced);

        $message = new Recovery()->recover($this->root);

        self::assertSame('The interrupted update was undone.', $message);
        $this->assertOldPackageInPlace();
        self::assertFalse(Recovery::pending($this->root));
    }

    public function testRecoveryKeepsWhatAppearedAtRetiredPath(): void
    {
        $this->simulateKilledUpdate(true, false);
        mkdir($this->root . '/themes/wonder');
        file_put_contents($this->root . '/themes/wonder/mine.php', 'mine');

        try {
            new Recovery()->recover($this->root);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('recovery_failed', $exception->errorCode);
        }

        self::assertSame('mine', file_get_contents($this->root . '/themes/wonder/mine.php'));
        self::assertTrue(Recovery::pending($this->root));
    }

    public function testRecoveryCleansUpCommittedRemoval(): void
    {
        $this->installOldPackage();
        $tx = Transaction::begin($this->root);
        $backup = '.yiipress/backup/' . $tx->tx . '/0';
        new Journal($tx->tx, 'remove', Journal::COMMITTED, [
            ['kind' => 'retire_dir', 'from' => 'themes/wonder', 'backup' => $backup],
        ])->write(new StatePaths($this->root));
        mkdir(dirname($this->root . '/' . $backup), 0o755, true);
        rename($this->root . '/themes/wonder', $this->root . '/' . $backup);

        new Recovery()->recover($this->root);

        self::assertFalse(file_exists($this->root . '/themes/wonder'));
        self::assertFalse(is_dir($this->root . '/.yiipress/backup/' . $tx->tx));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedRetireProvider(): iterable
    {
        yield 'backup outside the transaction' => [['from' => 'themes/wonder', 'backup' => 'themes/other']];
        yield 'not a package directory' => [['from' => 'content', 'backup' => '.yiipress/backup/0123456789abcdef/0']];
        yield 'no backup' => [['from' => 'themes/wonder']];
    }

    /**
     * @param array<string, mixed> $step
     */
    #[DataProvider('malformedRetireProvider')]
    public function testRecoveryRefusesMalformedRetireStep(array $step): void
    {
        mkdir($this->root . '/.yiipress');
        file_put_contents($this->root . '/.yiipress/journal.json', json_encode([
            'journal' => 1,
            'tx' => '0123456789abcdef',
            'operation' => 'remove',
            'state' => Journal::PREPARED,
            'steps' => [['kind' => 'retire_dir'] + $step],
        ]));

        try {
            new Recovery()->recover($this->root);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('recovery_failed', $exception->errorCode);
        }
    }

    public function testRecoveryRemovesFileAddedByKilledTransaction(): void
    {
        $this->simulateKilledAdd();

        $message = new Recovery()->recover($this->root);

        self::assertSame('The interrupted restore was undone.', $message);
        self::assertFileDoesNotExist($this->root . '/themes/wonder/entry.php');
        self::assertSame('kept', file_get_contents($this->root . '/themes/wonder/kept.php'));
        self::assertFalse(Recovery::pending($this->root));
    }

    public function testRecoveryKeepsChangedAddedFile(): void
    {
        $this->simulateKilledAdd();
        file_put_contents($this->root . '/themes/wonder/entry.php', 'edited');

        try {
            new Recovery()->recover($this->root);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('recovery_failed', $exception->errorCode);
        }

        self::assertSame('edited', file_get_contents($this->root . '/themes/wonder/entry.php'));
        self::assertTrue(Recovery::pending($this->root));
    }

    public function testRecoveryUndoesKilledTransaction(): void
    {
        $this->simulateKilledInstall(Journal::PREPARED);

        $message = new Recovery()->recover($this->root);

        self::assertSame('The interrupted install was undone.', $message);
        self::assertFalse(file_exists($this->root . '/themes/wonder'));
        self::assertFalse(file_exists($this->root . '/yiipress.lock'));
        self::assertSame("title: Blog\n", file_get_contents($this->root . '/content/config.yaml'));
        self::assertFalse(Recovery::pending($this->root));
    }

    public function testRecoveryCleansUpCommittedTransaction(): void
    {
        $this->simulateKilledInstall(Journal::COMMITTED);

        $message = new Recovery()->recover($this->root);

        self::assertSame('The interrupted install had completed; its temporary files were removed.', $message);
        self::assertTrue(is_dir($this->root . '/themes/wonder'));
        self::assertFalse(Recovery::pending($this->root));
    }

    public function testRecoveryKeepsChangedDirectory(): void
    {
        $this->simulateKilledInstall(Journal::PREPARED);
        file_put_contents($this->root . '/themes/wonder/entry.php', 'edited');

        try {
            new Recovery()->recover($this->root);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('recovery_failed', $exception->errorCode);
        }

        self::assertSame('edited', file_get_contents($this->root . '/themes/wonder/entry.php'));
        self::assertTrue(Recovery::pending($this->root));
    }

    public function testRecoveryRemovesInstalledDirectoryInOneStep(): void
    {
        $tx = Transaction::begin($this->root);
        $this->stagePackage($tx);
        mkdir($tx->stagingDirectory() . '/package/locked');
        file_put_contents($tx->stagingDirectory() . '/package/locked/file.php', 'x');
        $hashes = DirectoryHashes::of($tx->stagingDirectory() . '/package');
        new Journal($tx->tx, 'install', Journal::PREPARED, [
            ['kind' => 'move_dir', 'from' => $tx->stagingPath('package'), 'to' => 'themes/wonder', 'hashes' => $hashes],
        ])->write(new StatePaths($this->root));
        mkdir($this->root . '/themes');
        rename($tx->stagingDirectory() . '/package', $this->root . '/themes/wonder');
        // Deleting this file fails, as if the process were killed in the middle of deleting the directory.
        chmod($this->root . '/themes/wonder/locked', 0o555);

        try {
            new Recovery()->recover($this->root);
        } finally {
            foreach (glob($this->root . '/{themes/wonder,.yiipress/staging/*/*}/locked', GLOB_BRACE) ?: [] as $locked) {
                chmod($locked, 0o755);
            }
        }

        self::assertFalse(file_exists($this->root . '/themes/wonder'));
        self::assertFalse(Recovery::pending($this->root));
    }

    public function testRecoveryRemovesStagingOfKilledDownload(): void
    {
        $tx = Transaction::begin($this->root);
        file_put_contents($tx->stagingDirectory() . '/archive.tar.gz', 'partial download');
        mkdir($this->root . '/.yiipress/backup/' . $tx->tx, 0o755, true);
        // The process is killed before the journal is written, so no `finally` runs.
        $lock = MutationLock::acquire($this->root);

        $result = new Recovery()->recover($this->root);
        $lock->release();

        self::assertNotNull($result);
        self::assertSame([], glob($this->root . '/.yiipress/{staging,backup}/*', GLOB_BRACE));
        self::assertFileExists($this->root . '/.yiipress/mutation.lock');
    }

    public function testRecoveryRefusesMalformedJournal(): void
    {
        mkdir($this->root . '/.yiipress');
        file_put_contents($this->root . '/.yiipress/journal.json', '{"journal": 1, "steps": [{"kind": "move_dir", "to": "../x"}]}');

        $this->expectExceptionObject(new InstallException('recovery_failed', $this->root . '/.yiipress/journal.json is unreadable or malformed, so the interrupted extension operation cannot be undone automatically.'));

        new Recovery()->recover($this->root);
    }

    public function testNothingToRecover(): void
    {
        self::assertNull(new Recovery()->recover($this->root));
    }

    public function testMutationLockIsExclusive(): void
    {
        $lock = MutationLock::acquire($this->root);
        try {
            MutationLock::acquire($this->root);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame('mutation_locked', $exception->errorCode);
        }
        $lock->release();

        MutationLock::acquire($this->root)->release();
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, string>
     */
    private function stagePackage(Transaction $tx): array
    {
        mkdir($tx->stagingDirectory() . '/package/assets', 0o755, true);
        file_put_contents($tx->stagingDirectory() . '/package/entry.php', '<?php');
        file_put_contents($tx->stagingDirectory() . '/package/assets/style.css', 'body{}');

        return DirectoryHashes::of($tx->stagingDirectory() . '/package');
    }

    private function installOldPackage(): void
    {
        mkdir($this->root . '/themes/wonder', 0o755, true);
        file_put_contents($this->root . '/themes/wonder/old.php', 'old');
        file_put_contents($this->root . '/yiipress.lock', "{\"old\": true}\n");
    }

    private function assertOldPackageInPlace(): void
    {
        self::assertSame('old', file_get_contents($this->root . '/themes/wonder/old.php'));
        self::assertFileDoesNotExist($this->root . '/themes/wonder/entry.php');
        self::assertSame("{\"old\": true}\n", file_get_contents($this->root . '/yiipress.lock'));
    }

    /**
     * Leaves the project as an update killed before retiring the old package, after it, or after the new package
     * was moved into place.
     */
    private function simulateKilledUpdate(bool $retired, bool $replaced): void
    {
        $this->installOldPackage();
        $tx = Transaction::begin($this->root);
        $hashes = $this->stagePackage($tx);
        $backup = '.yiipress/backup/' . $tx->tx . '/0';
        mkdir($this->root . '/.yiipress/backup/' . $tx->tx, 0o755, true);
        new Journal($tx->tx, 'update', Journal::PREPARED, [
            ['kind' => 'retire_dir', 'from' => 'themes/wonder', 'backup' => $backup],
            ['kind' => 'move_dir', 'from' => $tx->stagingPath('package'), 'to' => 'themes/wonder', 'hashes' => $hashes],
            [
                'kind' => 'replace_file',
                'path' => 'yiipress.lock',
                'staged' => $tx->stagingPath('files/2'),
                'backup' => '.yiipress/backup/' . $tx->tx . '/2',
            ],
        ])->write(new StatePaths($this->root));
        file_put_contents($this->root . '/.yiipress/backup/' . $tx->tx . '/2', "{\"old\": true}\n");
        if ($retired) {
            rename($this->root . '/themes/wonder', $this->root . '/' . $backup);
        }
        if ($replaced) {
            rename($tx->stagingDirectory() . '/package', $this->root . '/themes/wonder');
        }
    }

    /**
     * Leaves the project as a restore killed after it put a missing file into an installed theme.
     */
    private function simulateKilledAdd(): void
    {
        mkdir($this->root . '/themes/wonder', 0o755, true);
        file_put_contents($this->root . '/themes/wonder/kept.php', 'kept');
        $tx = Transaction::begin($this->root);
        $this->stagePackage($tx);
        new Journal($tx->tx, 'restore', Journal::PREPARED, [[
            'kind' => 'add_file',
            'from' => $tx->stagingPath('package/entry.php'),
            'to' => 'themes/wonder/entry.php',
            'hash' => hash('sha256', '<?php'),
        ]])->write(new StatePaths($this->root));
        rename($tx->stagingDirectory() . '/package/entry.php', $this->root . '/themes/wonder/entry.php');
    }

    /**
     * Leaves the project as a process killed after step 12 (prepared) or before cleanup (committed) would.
     */
    private function simulateKilledInstall(string $state): void
    {
        $paths = new StatePaths($this->root);
        $tx = Transaction::begin($this->root);
        $hashes = $this->stagePackage($tx);
        mkdir($this->root . '/.yiipress/backup/' . $tx->tx, 0o755, true);
        file_put_contents($this->root . '/.yiipress/backup/' . $tx->tx . '/1', "title: Blog\n");
        new Journal($tx->tx, 'install', $state, [
            ['kind' => 'move_dir', 'from' => $tx->stagingPath('package'), 'to' => 'themes/wonder', 'hashes' => $hashes],
            ['kind' => 'replace_file', 'path' => 'yiipress.lock', 'staged' => $tx->stagingPath('files/1'), 'backup' => null],
            [
                'kind' => 'replace_file',
                'path' => 'content/config.yaml',
                'staged' => $tx->stagingPath('files/2'),
                'backup' => '.yiipress/backup/' . $tx->tx . '/1',
            ],
        ])->write($paths);
        mkdir($this->root . '/themes');
        rename($tx->stagingDirectory() . '/package', $this->root . '/themes/wonder');
        file_put_contents($this->root . '/yiipress.lock', "{}\n");
        file_put_contents($this->root . '/content/config.yaml', "theme: wonder\n");
    }
}

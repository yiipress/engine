<?php

declare(strict_types=1);

namespace YiiPress\Extension\Transaction;

use YiiPress\Extension\Install\InstallException;
use Yiisoft\Yii\Console\ExitCode;

use function fclose;
use function flock;
use function fopen;
use function sprintf;

use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;

/**
 * Exclusive lock for commands that change extensions of a project. The OS releases it when the process exits,
 * so the lock file existing alone means nothing.
 */
final class MutationLock
{
    /**
     * @param resource|null $handle
     */
    private function __construct(private mixed $handle) {}

    /**
     * @throws InstallException `mutation_locked` when another extension command runs in this project.
     */
    public static function acquire(string $projectRoot): self
    {
        $paths = new StatePaths($projectRoot);
        StatePaths::makeDirectory($paths->directory());
        $handle = @fopen($paths->mutationLock(), 'c');
        if ($handle === false) {
            throw new InstallException('filesystem_failed', sprintf('Could not open "%s".', $paths->mutationLock()));
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new InstallException(
                'mutation_locked',
                'Another YiiPress extension command is running in this project. Wait for it to finish and retry.',
                ExitCode::TEMPFAIL,
            );
        }

        return new self($handle);
    }

    public function release(): void
    {
        if ($this->handle === null) {
            return;
        }
        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}

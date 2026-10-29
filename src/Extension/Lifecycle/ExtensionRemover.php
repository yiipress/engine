<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lifecycle;

use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Transaction\MutationLock;
use YiiPress\Extension\Transaction\Recovery;
use YiiPress\Extension\Transaction\Transaction;

/**
 * Removes an installed extension: its recorded directory and its lock entry, in one transaction. Refuses while the
 * project uses it, then while it has local changes; it never edits configuration or content. When the whole
 * directory is already gone, only the lock entry is dropped (owner's decision on #256, 2026-10-29).
 */
final readonly class ExtensionRemover
{
    private function __construct(
        private LifecycleRulesInterface $rules,
        private Recovery $recovery,
    ) {}

    /**
     * @param string $contentDir Directory of `config.yaml`, relative to the project root or absolute.
     */
    public static function forTheme(string $contentDir, Recovery $recovery = new Recovery()): self
    {
        return new self(new ThemeLifecycleRules($contentDir), $recovery);
    }

    /**
     * @throws InstallException With the code of the problem; the extension and its lock entry stay unchanged.
     */
    public function remove(string $projectRoot, ExtensionId $id): RemoveResult
    {
        $mutationLock = MutationLock::acquire($projectRoot);
        try {
            $recovered = $this->recovery->recover($projectRoot);
            $lockFile = LockFile::read($projectRoot . '/' . LockFile::FILE_NAME);
            $locked = InstalledExtension::find($lockFile, $id, $this->rules);
            $this->rules->checkUnused($projectRoot, $locked);
            $present = InstalledExtension::unchangedOrGone($projectRoot, $locked, (string) $id, $this->rules);

            $transaction = Transaction::begin($projectRoot, 'remove');
            try {
                if ($present) {
                    $transaction->retireDirectory($locked->destination);
                }
                $transaction->replaceFile(LockFile::FILE_NAME, $lockFile->withoutExtension($id)->toJson());
                $transaction->commit();
            } finally {
                $transaction->discard();
            }
        } finally {
            $mutationLock->release();
        }

        return new RemoveResult($locked, $present, $recovered);
    }
}

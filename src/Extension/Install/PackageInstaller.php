<?php

declare(strict_types=1);

namespace YiiPress\Extension\Install;

use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\ReleaseSelector;
use YiiPress\Extension\Release\SelectedRelease;
use YiiPress\Extension\Transaction\MutationLock;
use YiiPress\Extension\Transaction\Recovery;
use YiiPress\Extension\Transaction\Transaction;

/**
 * Installs an extension from a GitHub release: selects the release, downloads and checks its archive, then moves
 * the package into place and updates `yiipress.lock` (and the activation file) in one transaction.
 *
 * No file of the package is included or executed.
 */
final readonly class PackageInstaller
{
    private PackageStager $stager;

    public function __construct(
        private GitHubReleaseSource $source,
        ?PackageStager $stager = null,
        private Recovery $recovery = new Recovery(),
    ) {
        $this->stager = $stager ?? new PackageStager($source);
    }

    /**
     * @throws InstallException With the code of the problem; project files stay unchanged.
     */
    public function install(InstallRequest $request, DestinationPolicyInterface $policy): InstallResult
    {
        $mutationLock = MutationLock::acquire($request->projectRoot);
        try {
            $recovered = $this->recovery->recover($request->projectRoot);
            $lockFile = LockFile::read($request->projectRoot . '/' . LockFile::FILE_NAME);
            $selected = new ReleaseSelector($this->source)->select(
                $request->repository,
                $request->type,
                $request->version,
                $request->engine,
                $request->platform,
            );

            $transaction = Transaction::begin($request->projectRoot);
            try {
                [$locked, $activation] = $this->stage($transaction, $request, $policy, $lockFile, $selected);
                $transaction->commit();
            } finally {
                $transaction->discard();
            }
        } finally {
            $mutationLock->release();
        }

        return new InstallResult($selected, $locked, $activation?->path, $recovered);
    }

    /**
     * Downloads, checks and records every change in the transaction; changes no project file.
     *
     * @return array{LockedExtension, FileChange|null}
     */
    private function stage(
        Transaction $transaction,
        InstallRequest $request,
        DestinationPolicyInterface $policy,
        LockFile $lockFile,
        SelectedRelease $selected,
    ): array {
        $staged = $this->stager->stage($transaction, $request, $selected);
        $manifest = $staged->manifest;
        $destination = $policy->destination($manifest, $lockFile, $request->projectRoot);
        $activation = $policy->activation($manifest, $request->projectRoot);
        $locked = $staged->locked($destination, $policy->localName($manifest));

        $transaction->moveDirectory($staged->path, $destination, $staged->files);
        $transaction->replaceFile(LockFile::FILE_NAME, $lockFile->withExtension($manifest->id, $locked)->toJson());
        if ($activation !== null) {
            $transaction->replaceFile($activation->path, $activation->contents);
        }

        return [$locked, $activation];
    }
}

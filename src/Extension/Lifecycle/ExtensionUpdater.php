<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lifecycle;

use Composer\Semver\Comparator;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Install\InstallRequest;
use YiiPress\Extension\Install\PackageStager;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\GitHubRepository;
use YiiPress\Extension\Release\ReleaseSelector;
use YiiPress\Extension\Release\SelectedRelease;
use YiiPress\Extension\Transaction\MutationLock;
use YiiPress\Extension\Transaction\Recovery;
use YiiPress\Extension\Transaction\Transaction;

use function sprintf;

/**
 * Replaces an installed extension with another release from its recorded source: the newest compatible stable
 * release, or an exact version (a downgrade too). The package files and the lock entry change in one transaction;
 * the extension keeps its name and directory, so configuration and content that use it stay as they are.
 */
final readonly class ExtensionUpdater
{
    private PackageStager $stager;

    private function __construct(
        private GitHubReleaseSource $source,
        private LifecycleRulesInterface $rules,
        ?PackageStager $stager,
        private Recovery $recovery,
    ) {
        $this->stager = $stager ?? new PackageStager($source);
    }

    public static function forTheme(
        GitHubReleaseSource $source,
        ?PackageStager $stager = null,
        Recovery $recovery = new Recovery(),
    ): self {
        return new self($source, new ThemeLifecycleRules(), $stager, $recovery);
    }

    public static function forProcessor(
        GitHubReleaseSource $source,
        ?PackageStager $stager = null,
        Recovery $recovery = new Recovery(),
    ): self {
        return new self($source, new ProcessorLifecycleRules(), $stager, $recovery);
    }

    /**
     * @param string|null $version Exact `X.Y.Z`; `null` takes the newest compatible release when it is newer.
     *
     * @throws InstallException With the code of the problem; the installed extension and its lock entry stay
     *     unchanged.
     */
    public function update(
        string $projectRoot,
        ExtensionId $id,
        ?string $version,
        EngineVersion $engine,
        Platform $platform,
    ): UpdateResult {
        $mutationLock = MutationLock::acquire($projectRoot);
        try {
            $recovered = $this->recovery->recover($projectRoot);
            $lockFile = LockFile::read($projectRoot . '/' . LockFile::FILE_NAME);
            $type = $this->rules->type();
            $previous = InstalledExtension::unchanged(
                $projectRoot,
                $lockFile,
                $id,
                'update:' . $type->value,
                $this->rules,
            );
            $request = new InstallRequest(
                $projectRoot,
                GitHubRepository::fromUrl($previous->source),
                $type,
                $version,
                $engine,
                $platform,
            );
            $selected = new ReleaseSelector($this->source)->select(
                $request->repository,
                $request->type,
                $request->version,
                $request->engine,
                $request->platform,
            );
            self::checkSameExtension($id, $selected, $request->repository);
            if (!self::changes($previous, $selected, $version)) {
                return new UpdateResult($previous, $previous, $selected, $recovered);
            }
            $this->rules->checkUpdate($projectRoot, $previous, $selected->manifest);

            $transaction = Transaction::begin($projectRoot, 'update');
            try {
                $current = $this->stage($transaction, $request, $selected, $lockFile, $previous);
                $transaction->commit();
            } finally {
                $transaction->discard();
            }
        } finally {
            $mutationLock->release();
        }

        return new UpdateResult($previous, $current, $selected, $recovered);
    }

    /**
     * Without an exact version, an update never goes back to an older release.
     */
    private static function changes(LockedExtension $previous, SelectedRelease $selected, ?string $version): bool
    {
        $next = $selected->release->version;

        return $version === null
            ? Comparator::greaterThan($next, $previous->version)
            : $next !== $previous->version;
    }

    private static function checkSameExtension(
        ExtensionId $id,
        SelectedRelease $selected,
        GitHubRepository $repository,
    ): void {
        if ((string) $selected->manifest->id !== (string) $id) {
            $type = $selected->manifest->type->value;
            throw new InstallException('manifest_mismatch', sprintf(
                'Release %s of %s is %s, not the installed %s %s.',
                $selected->release->tag,
                $repository,
                $selected->manifest->id,
                $type,
                $id,
            ), solution: sprintf(
                'Remove the %1$s and install the other one with get:%1$s if you want to switch.',
                $type,
            ));
        }
    }

    /**
     * Downloads and checks the release, then records the replacement; changes no project file.
     */
    private function stage(
        Transaction $transaction,
        InstallRequest $request,
        SelectedRelease $selected,
        LockFile $lockFile,
        LockedExtension $previous,
    ): LockedExtension {
        $staged = $this->stager->stage($transaction, $request, $selected);
        $current = $staged->locked($previous->destination, $previous->name);

        $transaction->retireDirectory($previous->destination);
        $transaction->moveDirectory($staged->path, $previous->destination, $staged->files);
        $transaction->replaceFile(
            LockFile::FILE_NAME,
            $lockFile->withExtension($staged->manifest->id, $current)->toJson(),
        );

        return $current;
    }
}

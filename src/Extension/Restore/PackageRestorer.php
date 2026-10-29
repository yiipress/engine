<?php

declare(strict_types=1);

namespace YiiPress\Extension\Restore;

use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockedExtensionParser;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Theme\ThemeDestinationPolicy;
use YiiPress\Extension\Transaction\JournalPaths;
use YiiPress\Extension\Transaction\MutationLock;
use YiiPress\Extension\Transaction\Recovery;
use YiiPress\Extension\Transaction\StatePaths;
use YiiPress\Extension\Transaction\Transaction;
use Yiisoft\Yii\Console\ExitCode;

use function array_values;
use function implode;
use function in_array;
use function ksort;
use function mb_strtolower;
use function sprintf;
use function strlen;
use function substr;

use const SORT_STRING;

/**
 * Puts every package of `yiipress.lock` back into a project exactly as locked: missing package directories and
 * missing files come from the locked archive; matching packages are skipped.
 *
 * It never selects another release, activates a package, edits configuration or writes the lock. Modified or
 * unexpected files, destination collisions and lock data it cannot use stop it before anything is downloaded;
 * archive problems stop it before anything is changed. Files are never overwritten or deleted.
 */
final readonly class PackageRestorer
{
    private LockedArchive $archive;

    public function __construct(
        GitHubReleaseSource $source,
        private Recovery $recovery = new Recovery(),
    ) {
        $this->archive = new LockedArchive($source);
    }

    /**
     * @throws RestoreRefusedException Every problem found; the project is unchanged.
     * @throws InstallException Lock, mutation lock, recovery, network and file system errors.
     */
    public function restore(string $projectRoot, EngineVersion $engine, Platform $platform): RestoreResult
    {
        $mutationLock = MutationLock::acquire($projectRoot);
        try {
            $recovered = $this->recovery->recover($projectRoot);
            $extensions = LockFile::read($projectRoot . '/' . LockFile::FILE_NAME)->extensions();
            ksort($extensions, SORT_STRING);
            $installed = self::inspect($projectRoot, $extensions);

            $packages = [];
            $repairs = [];
            foreach ($extensions as $id => $locked) {
                if ($installed[$id]->isIntact()) {
                    $packages[$id] = new RestoredPackage($id, $locked->destination, RestoredPackage::INTACT);
                } else {
                    $repairs[$id] = $locked;
                }
            }
            if ($repairs !== []) {
                $packages = [...$packages, ...$this->repair($projectRoot, $repairs, $installed, $engine, $platform)];
                ksort($packages, SORT_STRING);
            }
        } finally {
            $mutationLock->release();
        }

        return new RestoreResult(array_values($packages), $recovered);
    }

    /**
     * Compares every package with the lock; changes nothing and downloads nothing.
     *
     * @param array<string, LockedExtension> $extensions
     * @return array<string, InstalledPackage>
     *
     * @throws RestoreRefusedException
     */
    private static function inspect(string $projectRoot, array $extensions): array
    {
        $problems = [];
        $installed = [];
        $owners = [];
        foreach ($extensions as $id => $locked) {
            $problem = self::destinationProblem($id, $locked);
            $key = mb_strtolower($locked->destination);
            if ($problem === null && isset($owners[$key])) {
                $problem = new InstallException('name_collision', sprintf(
                    'yiipress.lock installs both %s and %s into "%s".',
                    $owners[$key],
                    $id,
                    $locked->destination,
                ), solution: 'Remove one of the two entries from yiipress.lock.');
            }
            if ($problem !== null) {
                $problems[] = $problem;
                continue;
            }
            $owners[$key] = $id;
            $installed[$id] = InstalledPackage::inspect($projectRoot, $locked);
            $problems = [...$problems, ...self::installedProblems($id, $locked, $installed[$id])];
        }
        if ($problems !== []) {
            throw new RestoreRefusedException($problems);
        }

        return $installed;
    }

    private static function destinationProblem(string $id, LockedExtension $locked): ?InstallException
    {
        if ($locked->type !== ExtensionType::Theme) {
            return LockedExtensionParser::invalid(
                "extensions.$id.type",
                sprintf('is "%s", which this YiiPress cannot restore yet', $locked->type->value),
            );
        }
        $expected = ThemeDestinationPolicy::THEMES_DIR . '/' . $locked->name;
        if ($locked->destination !== $expected || !JournalPaths::isDirectoryTarget($expected)) {
            return LockedExtensionParser::invalid(
                "extensions.$id.destination",
                sprintf('must be "%s" for theme "%s"', $expected, $locked->name),
            );
        }

        return null;
    }

    /**
     * @return list<InstallException>
     */
    private static function installedProblems(string $id, LockedExtension $locked, InstalledPackage $installed): array
    {
        $describe = sprintf('%s %s', $id, $locked->version);
        $solution = 'Move your files out of "%s" (keep a copy of your changes), then run restore:extensions again.';
        $problems = [];
        if ($installed->collision !== null) {
            $problems[] = new InstallException('destination_exists', sprintf(
                '"%s" takes the place of %s, which belongs in "%s".',
                $installed->collision,
                $describe,
                $locked->destination,
            ), solution: sprintf('Rename or remove "%s", then run restore:extensions again.', $installed->collision));
        }
        if ($installed->modified !== []) {
            $problems[] = new InstallException('files_modified', sprintf(
                'Files of %s in "%s" differ from the locked release: %s.',
                $describe,
                $locked->destination,
                implode(', ', $installed->modified),
            ), solution: sprintf($solution, $locked->destination));
        }
        if ($installed->unexpected !== []) {
            $problems[] = new InstallException('files_unexpected', sprintf(
                '"%s" holds files that are not part of %s: %s.',
                $locked->destination,
                $describe,
                implode(', ', $installed->unexpected),
            ), solution: sprintf($solution, $locked->destination));
        }

        return $problems;
    }

    /**
     * Downloads and checks every archive, then installs missing directories and files in one transaction.
     *
     * @param array<string, LockedExtension> $repairs
     * @param array<string, InstalledPackage> $installed
     * @return array<string, RestoredPackage>
     */
    private function repair(
        string $projectRoot,
        array $repairs,
        array $installed,
        EngineVersion $engine,
        Platform $platform,
    ): array {
        $transaction = Transaction::begin($projectRoot, 'restore');
        try {
            $problems = [];
            $packages = [];
            foreach ($repairs as $id => $locked) {
                try {
                    $packages[$id] = $this->stage($transaction, $id, $locked, $installed[$id], $engine, $platform);
                } catch (InstallException $exception) {
                    if (in_array($exception->getCode(), [ExitCode::UNAVAILABLE, ExitCode::TEMPFAIL], true)) {
                        throw $exception;
                    }
                    $problems[] = $exception;
                }
            }
            if ($problems !== []) {
                throw new RestoreRefusedException($problems);
            }
            $transaction->commit();
        } finally {
            $transaction->discard();
        }

        return $packages;
    }

    private function stage(
        Transaction $transaction,
        string $id,
        LockedExtension $locked,
        InstalledPackage $installed,
        EngineVersion $engine,
        Platform $platform,
    ): RestoredPackage {
        $staging = $transaction->stagingDirectory() . '/' . $id;
        StatePaths::makeDirectory($staging);
        $verified = $this->archive->fetch($id, $locked, $staging, $engine, $platform);
        $package = $transaction->stagingPath(
            substr($verified->directory, strlen($transaction->stagingDirectory()) + 1),
        );

        if (!$installed->exists) {
            $transaction->moveDirectory($package, $locked->destination, $locked->files);

            return new RestoredPackage(
                $id,
                $locked->destination,
                RestoredPackage::INSTALLED,
                warning: $verified->warning,
            );
        }
        foreach ($installed->missing as $file) {
            $transaction->addFile($package . '/' . $file, $locked->destination . '/' . $file, $locked->files[$file]);
        }

        return new RestoredPackage(
            $id,
            $locked->destination,
            RestoredPackage::REPAIRED,
            $installed->missing,
            $verified->warning,
        );
    }
}

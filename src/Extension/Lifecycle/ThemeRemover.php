<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lifecycle;

use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Transaction\MutationLock;
use YiiPress\Extension\Transaction\Recovery;
use YiiPress\Extension\Transaction\Transaction;

use function array_map;
use function implode;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Removes an installed theme: its recorded directory and its lock entry, in one transaction. Refuses while the theme
 * has local changes or the site or its content selects it; it never edits content to switch themes.
 */
final readonly class ThemeRemover
{
    public function __construct(
        private ThemeReferences $references = new ThemeReferences(),
        private Recovery $recovery = new Recovery(),
    ) {}

    /**
     * @param string $contentDir Directory of `config.yaml`, relative to the project root or absolute.
     *
     * @throws InstallException With the code of the problem; the theme and its lock entry stay unchanged.
     */
    public function remove(string $projectRoot, ExtensionId $id, string $contentDir): RemoveResult
    {
        $mutationLock = MutationLock::acquire($projectRoot);
        try {
            $recovered = $this->recovery->recover($projectRoot);
            $lockFile = LockFile::read($projectRoot . '/' . LockFile::FILE_NAME);
            $locked = InstalledTheme::unchanged($projectRoot, $lockFile, $id, 'remove:theme');
            $this->checkUnused($projectRoot, (string) $locked->name, $contentDir);

            $transaction = Transaction::begin($projectRoot, 'remove');
            try {
                $transaction->retireDirectory($locked->destination);
                $transaction->replaceFile(LockFile::FILE_NAME, $lockFile->withoutExtension($id)->toJson());
                $transaction->commit();
            } finally {
                $transaction->discard();
            }
        } finally {
            $mutationLock->release();
        }

        return new RemoveResult($locked, $recovered);
    }

    private function checkUnused(string $projectRoot, string $name, string $contentDir): void
    {
        $root = rtrim($projectRoot, '/');
        $absolute = str_starts_with($contentDir, '/') ? rtrim($contentDir, '/') : $root . '/' . rtrim($contentDir, '/');
        $files = $this->references->find($name, $absolute);
        if ($files === []) {
            return;
        }

        throw new InstallException('theme_in_use', sprintf(
            'Theme "%s" is still selected by %s.',
            $name,
            implode(', ', array_map(
                static fn(string $file): string => str_starts_with($file, $root . '/')
                    ? substr($file, strlen($root) + 1)
                    : $file,
                $files,
            )),
        ), solution: sprintf(
            'Set "theme" in config.yaml to another theme and change or remove "theme: %s" in the listed entries, '
            . 'then run remove:theme again. YiiPress does not change content for you.',
            $name,
        ));
    }
}

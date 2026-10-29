<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lifecycle;

use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Manifest\ExtensionManifest;
use YiiPress\Extension\Manifest\ProcessorEntryPoint;
use YiiPress\Extension\Processor\LockedProcessorDestination;
use YiiPress\Extension\Processor\ProcessorReference;
use YiiPress\Extension\Processor\ProcessorReferences;

use function array_map;
use function implode;
use function rtrim;
use function sprintf;

/**
 * Processors live in `<content-dir>/extensions/processors/<id>`; one is in use while `processors` in the
 * `config.yaml` of that content directory points into it, and an update must keep what it enables.
 *
 * @internal
 */
final readonly class ProcessorLifecycleRules implements LifecycleRulesInterface
{
    public function __construct(private ProcessorReferences $references = new ProcessorReferences()) {}

    public function type(): ExtensionType
    {
        return ExtensionType::Processor;
    }

    public function destinationProblem(string $id, LockedExtension $locked): ?InstallException
    {
        return LockedProcessorDestination::problem($id, $locked);
    }

    public function checkUnused(string $projectRoot, LockedExtension $locked): void
    {
        $references = $this->references->find(
            self::contentDir($projectRoot, $locked),
            rtrim($projectRoot, '/') . '/' . $locked->destination,
        );
        if ($references === []) {
            return;
        }

        $config = self::configLabel($locked);
        throw new InstallException('extension_in_use', sprintf(
            'Processor "%s" is still enabled in %s: %s.',
            $locked->destination,
            $config,
            implode('; ', array_map(
                static fn(ProcessorReference $reference): string => (string) $reference,
                $references,
            )),
        ), solution: sprintf(
            'Remove these paths from "processors" in %s, then run remove:processor again. '
            . 'YiiPress does not change configuration for you.',
            $config,
        ));
    }

    /**
     * Strict (lead decision on #256, rev 82): every enabled path must stay an entry point of the release and keep
     * declaring the stage it is enabled for, else the update is refused before any file changes.
     */
    public function checkUpdate(string $projectRoot, LockedExtension $previous, ExtensionManifest $next): void
    {
        $references = $this->references->find(
            self::contentDir($projectRoot, $previous),
            rtrim($projectRoot, '/') . '/' . $previous->destination,
        );
        $entryPoints = $next->processor()->entryPoints ?? [];
        $undeclared = [];
        foreach ($references as $reference) {
            if (!self::declares($entryPoints, $reference)) {
                $undeclared[] = sprintf(
                    '%s (no entry point "%s" for %s)',
                    $reference,
                    $reference->packagePath,
                    $reference->stage,
                );
            }
        }
        if ($undeclared === []) {
            return;
        }

        $config = self::configLabel($previous);
        throw new InstallException('entry_point_in_use', sprintf(
            'Release %s of processor %s does not declare what %s enables: %s.',
            $next->version,
            $next->id,
            $config,
            implode('; ', $undeclared),
        ), solution: sprintf(
            'Change "processors" in %s to entry points and stages that %s declares in its extension.yaml, '
            . 'or pick another release with --version. YiiPress does not change configuration for you.',
            $config,
            $next->version,
        ));
    }

    /**
     * @param list<ProcessorEntryPoint> $entryPoints
     */
    private static function declares(array $entryPoints, ProcessorReference $reference): bool
    {
        foreach ($entryPoints as $entryPoint) {
            if ($entryPoint->path !== $reference->packagePath) {
                continue;
            }
            foreach ($entryPoint->stages as $stage) {
                if ($stage->value === $reference->stage) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function contentDir(string $projectRoot, LockedExtension $locked): string
    {
        return rtrim($projectRoot, '/') . '/' . LockedProcessorDestination::contentDir($locked);
    }

    private static function configLabel(LockedExtension $locked): string
    {
        $contentDir = LockedProcessorDestination::contentDir($locked);

        return ($contentDir === '.' ? '' : $contentDir . '/') . 'config.yaml';
    }
}

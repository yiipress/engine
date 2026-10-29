<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lifecycle;

use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Manifest\ExtensionManifest;
use YiiPress\Extension\Processor\LockedProcessorDestination;
use YiiPress\Extension\Processor\ProcessorReferences;

use function implode;
use function rtrim;
use function sprintf;

/**
 * Processors live in `<content-dir>/extensions/processors/<id>`; one is in use while `processors` in the
 * `config.yaml` of that content directory points into it.
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
            implode('; ', $references),
        ), solution: sprintf(
            'Remove these paths from "processors" in %s, then run remove:processor again. '
            . 'YiiPress does not change configuration for you.',
            $config,
        ));
    }

    public function checkUpdate(string $projectRoot, LockedExtension $previous, ExtensionManifest $next): void {}

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

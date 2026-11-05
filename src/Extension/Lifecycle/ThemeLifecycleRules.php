<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lifecycle;

use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Manifest\ExtensionManifest;
use YiiPress\Extension\Theme\LockedThemeDestination;

use function array_map;
use function implode;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Themes live in `themes/<name>`; one is in use while `config.yaml` or content selects it.
 *
 * @internal
 */
final readonly class ThemeLifecycleRules implements LifecycleRulesInterface
{
    /**
     * @param string $contentDir Directory of `config.yaml`, relative to the project root or absolute.
     */
    public function __construct(
        private string $contentDir = 'content',
        private ThemeReferences $references = new ThemeReferences(),
    ) {}

    public function type(): ExtensionType
    {
        return ExtensionType::Theme;
    }

    public function destinationProblem(string $id, LockedExtension $locked): ?InstallException
    {
        return LockedThemeDestination::problem($id, $locked);
    }

    public function checkUnused(string $projectRoot, LockedExtension $locked): void
    {
        $name = (string) $locked->name;
        $root = rtrim($projectRoot, '/');
        $absolute = str_starts_with($this->contentDir, '/')
            ? rtrim($this->contentDir, '/')
            : $root . '/' . rtrim($this->contentDir, '/');
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

    public function checkUpdate(string $projectRoot, LockedExtension $previous, ExtensionManifest $next): void {}
}

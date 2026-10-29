<?php

declare(strict_types=1);

namespace YiiPress\Extension\Theme;

use YiiPress\Extension\Install\DestinationPolicyInterface;
use YiiPress\Extension\Install\FileChange;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Manifest\ExtensionManifest;

use function explode;
use function file_get_contents;
use function in_array;
use function is_dir;
use function is_file;
use function mb_strtolower;
use function rtrim;
use function scandir;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Themes go to `themes/<name>`, where `<name>` is the name part of the theme ID; activation sets `theme` in
 * `config.yaml`.
 */
final readonly class ThemeDestinationPolicy implements DestinationPolicyInterface
{
    public const string THEMES_DIR = 'themes';

    /**
     * @param string $contentDir Directory of `config.yaml`, relative to the project root or absolute inside it.
     * @param bool $activate Whether to make the installed theme the site theme.
     */
    public function __construct(
        private string $contentDir = 'content',
        private bool $activate = true,
        private ThemeActivator $activator = new ThemeActivator(),
    ) {}

    public function localName(ExtensionManifest $manifest): string
    {
        return $manifest->id->name;
    }

    public function destination(ExtensionManifest $manifest, LockFile $lock, string $projectRoot): string
    {
        $name = $this->localName($manifest);
        if ($lock->get($manifest->id) !== null) {
            throw new InstallException('already_installed', sprintf(
                'Theme %s is already installed; use update:theme to change its version.',
                $manifest->id,
            ));
        }
        foreach ($lock->extensions() as $id => $extension) {
            if ($extension->name === $name) {
                throw new InstallException('name_collision', sprintf(
                    'Theme %s would be named "%s", which installed theme %s already uses.',
                    $manifest->id,
                    $name,
                    $id,
                ));
            }
        }

        $themesDir = $projectRoot . '/' . self::THEMES_DIR;
        $entries = is_dir($themesDir) ? scandir($themesDir) : [];
        foreach ($entries === false ? [] : $entries as $entry) {
            if (mb_strtolower($entry) === $name) {
                throw new InstallException('destination_exists', sprintf(
                    '"%s/%s" already exists; nothing was installed. Rename or remove it first.',
                    self::THEMES_DIR,
                    $entry,
                ));
            }
        }

        return self::THEMES_DIR . '/' . $name;
    }

    public function activation(ExtensionManifest $manifest, string $projectRoot): ?FileChange
    {
        if (!$this->activate) {
            return null;
        }

        $path = $this->relativeContentDir($projectRoot) . 'config.yaml';
        $absolute = $projectRoot . '/' . $path;
        $contents = is_file($absolute) ? @file_get_contents($absolute) : false;
        if ($contents === false) {
            throw new InstallException(
                'config_missing',
                sprintf('There is no %s to activate theme %s in.', $path, $manifest->id),
                solution: 'Pass --content-dir with the directory of config.yaml, or --no-activate.',
            );
        }

        return new FileChange($path, $this->activator->activate($contents, $this->localName($manifest)));
    }

    /**
     * @return string Content directory relative to the project root with a trailing `/`, or empty for the root.
     */
    private function relativeContentDir(string $projectRoot): string
    {
        $dir = rtrim($this->contentDir, '/');
        if (str_starts_with($dir, '/')) {
            $root = rtrim($projectRoot, '/') . '/';
            if ($dir . '/' === $root) {
                return '';
            }
            if (!str_starts_with($dir, $root)) {
                throw new InstallException('config_unsupported', sprintf(
                    'The content directory "%s" is outside the project "%s", so its config.yaml cannot be changed '
                    . 'together with the installation.',
                    $this->contentDir,
                    $projectRoot,
                ), solution: 'Use --no-activate and set "theme" in config.yaml by hand.');
            }
            $dir = substr($dir, strlen($root));
        }
        $dir = trim($dir, '/');
        if (in_array('..', explode('/', $dir), true)) {
            throw new InstallException('config_unsupported', sprintf(
                'The content directory "%s" must not contain "..".',
                $this->contentDir,
            ), solution: 'Use --no-activate and set "theme" in config.yaml by hand.');
        }

        return $dir === '' || $dir === '.' ? '' : $dir . '/';
    }
}

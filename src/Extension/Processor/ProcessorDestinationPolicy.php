<?php

declare(strict_types=1);

namespace YiiPress\Extension\Processor;

use YiiPress\Extension\Install\ContentDirectory;
use YiiPress\Extension\Install\DestinationPolicyInterface;
use YiiPress\Extension\Install\FileChange;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Manifest\ExtensionManifest;

use function is_dir;
use function mb_strtolower;
use function scandir;
use function sprintf;

/**
 * Processors go to `<content-dir>/extensions/processors/<vendor>/<name>`, outside the discovered
 * `processors/*.php`. Installation enables nothing: the site lists entry points in `processors` itself.
 */
final readonly class ProcessorDestinationPolicy implements DestinationPolicyInterface
{
    public const string PROCESSORS_DIR = 'extensions/processors';

    /**
     * @param string $contentDir Directory of `config.yaml`, relative to the project root or absolute inside it.
     */
    public function __construct(private string $contentDir = 'content') {}

    public function localName(ExtensionManifest $manifest): ?string
    {
        return null;
    }

    public function destination(ExtensionManifest $manifest, LockFile $lock, string $projectRoot): string
    {
        if ($lock->get($manifest->id) !== null) {
            throw new InstallException('already_installed', sprintf(
                'Processor %s is already installed; use update:processor to change its version.',
                $manifest->id,
            ));
        }
        $base = ContentDirectory::relative(
            $this->contentDir,
            $projectRoot,
            'processors cannot be installed into it',
            'Pass --content-dir with the content directory of the project.',
        ) . self::PROCESSORS_DIR;

        $path = $base;
        foreach ([$manifest->id->vendor, $manifest->id->name] as $segment) {
            $existing = self::entry($projectRoot . '/' . $path, $segment);
            if ($existing === null) {
                break;
            }
            $path .= '/' . $existing;
            if ($segment === $manifest->id->name) {
                throw new InstallException('destination_exists', sprintf(
                    '"%s" already exists; nothing was installed. Rename or remove it first.',
                    $path,
                ));
            }
        }

        return $base . '/' . $manifest->id->vendor . '/' . $manifest->id->name;
    }

    public function activation(ExtensionManifest $manifest, string $projectRoot): ?FileChange
    {
        return null;
    }

    /**
     * The entry of a directory named like the segment in any case, `null` when there is none.
     */
    private static function entry(string $directory, string $segment): ?string
    {
        $entries = is_dir($directory) ? scandir($directory) : [];
        foreach ($entries === false ? [] : $entries as $entry) {
            if (mb_strtolower($entry) === $segment) {
                return $entry;
            }
        }

        return null;
    }
}

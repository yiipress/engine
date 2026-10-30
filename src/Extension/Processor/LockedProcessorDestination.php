<?php

declare(strict_types=1);

namespace YiiPress\Extension\Processor;

use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockedExtensionParser;
use YiiPress\Extension\Transaction\JournalPaths;

use function array_slice;
use function explode;
use function implode;
use function sprintf;
use function str_ends_with;

/**
 * A processor belongs in `<content-dir>/extensions/processors/<id>`; a lock entry that points anywhere else is not
 * trusted. The lock keeps no content directory of its own: it is the part of the destination before that suffix.
 */
final class LockedProcessorDestination
{
    /** `extensions/processors/<vendor>/<name>` */
    private const int SUFFIX_SEGMENTS = 4;

    public static function problem(string $id, LockedExtension $locked): ?InstallException
    {
        $suffix = ProcessorDestinationPolicy::PROCESSORS_DIR . '/' . $id;
        $destination = $locked->destination;
        $trusted = ($destination === $suffix || str_ends_with($destination, '/' . $suffix))
            && JournalPaths::isDirectoryTarget($destination);
        if (!$trusted) {
            return LockedExtensionParser::invalid(
                "extensions.$id.destination",
                sprintf('must be "<content directory>/%s" for processor %s', $suffix, $id),
            );
        }

        return null;
    }

    /**
     * @param LockedExtension $locked An entry {@see problem()} trusts.
     * @return string The content directory relative to the project root, `.` for the root itself.
     */
    public static function contentDir(LockedExtension $locked): string
    {
        $segments = array_slice(explode('/', $locked->destination), 0, -self::SUFFIX_SEGMENTS);

        return $segments === [] ? '.' : implode('/', $segments);
    }
}

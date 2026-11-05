<?php

declare(strict_types=1);

namespace YiiPress\Extension;

/**
 * Kind of extension package, as written in the `type` field of `extension.yaml`.
 */
enum ExtensionType: string
{
    case Theme = 'theme';
    case Processor = 'processor';
    case Importer = 'importer';
    case Plugin = 'plugin';

    /**
     * Whether this version of YiiPress can install packages of this type.
     * `importer` and `plugin` are reserved for later manifest versions.
     */
    public function isSupported(): bool
    {
        return $this === self::Theme || $this === self::Processor;
    }
}

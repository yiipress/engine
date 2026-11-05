<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

/**
 * Theme used for templates the extension theme does not provide.
 */
enum ThemeFallback: string
{
    case Minimal = 'minimal';
    case None = 'none';
}

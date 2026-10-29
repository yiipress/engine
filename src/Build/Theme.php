<?php

declare(strict_types=1);

namespace YiiPress\Build;

final readonly class Theme
{
    /**
     * @param bool $managed Installed by `get:theme` (its directory is in `yiipress.lock`). A managed theme is used
     *        only when it is the requested theme, never as a fallback for another one.
     */
    public function __construct(
        public string $name,
        public string $path,
        public bool $managed = false,
    ) {}
}

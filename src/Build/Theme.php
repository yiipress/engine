<?php

declare(strict_types=1);

namespace YiiPress\Build;

final readonly class Theme
{
    /**
     * The theme that ships with YiiPress.
     */
    public const string BUNDLED = 'minimal';

    /**
     * The theme made of the project's `content/templates/`.
     */
    public const string LOCAL = 'local';

    /**
     * @param list<string>|null $fallback Themes tried in this order for a file the theme does not have. `null` for a
     *        theme without a manifest: every other registered theme that is not managed is tried, in registration
     *        order. A list for a managed theme (installed by `get:theme`, its directory is in `yiipress.lock`), which
     *        is never tried for another theme.
     */
    public function __construct(
        public string $name,
        public string $path,
        public ?array $fallback = null,
    ) {}

    public function managed(): bool
    {
        return $this->fallback !== null;
    }
}

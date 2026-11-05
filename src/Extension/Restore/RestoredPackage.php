<?php

declare(strict_types=1);

namespace YiiPress\Extension\Restore;

/**
 * What a restore did with one locked package.
 */
final readonly class RestoredPackage
{
    /** Every locked file was in place; nothing was downloaded. */
    public const string INTACT = 'intact';
    /** The package directory was missing and was installed from the locked archive. */
    public const string INSTALLED = 'installed';
    /** Missing files were put back into the package directory. */
    public const string REPAIRED = 'repaired';

    /**
     * @param string $status {@see INTACT}, {@see INSTALLED} or {@see REPAIRED}.
     * @param list<string> $files Files put back, relative to the destination; empty unless {@see REPAIRED}.
     * @param string|null $warning The locked archive bytes changed while its contents did not.
     */
    public function __construct(
        public string $id,
        public string $destination,
        public string $status,
        public array $files = [],
        public ?string $warning = null,
    ) {}
}

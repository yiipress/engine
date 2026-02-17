<?php

declare(strict_types=1);

namespace App\Import;

final readonly class ImportResult
{
    /**
     * @param list<string> $importedFiles
     * @param list<string> $skippedFiles
     * @param list<string> $warnings
     */
    public function __construct(
        private int $totalMessages,
        private int $importedCount,
        private array $importedFiles,
        private array $skippedFiles,
        private array $warnings,
    ) {}

    public function totalMessages(): int
    {
        return $this->totalMessages;
    }

    public function importedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * @return list<string>
     */
    public function importedFiles(): array
    {
        return $this->importedFiles;
    }

    /**
     * @return list<string>
     */
    public function skippedFiles(): array
    {
        return $this->skippedFiles;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}

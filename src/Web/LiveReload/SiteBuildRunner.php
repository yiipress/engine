<?php

declare(strict_types=1);

namespace App\Web\LiveReload;

final class SiteBuildRunner
{
    public function __construct(
        private string $yiiBinary,
        private string $contentDir,
        private string $outputDir,
    ) {}

    public function build(): bool
    {
        $command = $this->yiiBinary
            . ' build'
            . ' --content-dir=' . escapeshellarg($this->contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' --no-cache'
            . ' 2>&1';

        exec($command, $output, $exitCode);

        return $exitCode === 0;
    }
}

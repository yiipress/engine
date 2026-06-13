<?php

declare(strict_types=1);

namespace YiiPress\Web\LiveReload;

final readonly class SiteBuildRunner
{
    public function __construct(
        private string $yiiBinary,
        private string $contentDir,
        private string $outputDir,
    ) {}

    public function build(): SiteBuildResult
    {
        $command = escapeshellarg($this->yiiBinary)
            . ' build'
            . ' --content-dir=' . escapeshellarg($this->contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1';

        exec($command, $output, $exitCode);

        return new SiteBuildResult($exitCode, implode("\n", $output));
    }
}

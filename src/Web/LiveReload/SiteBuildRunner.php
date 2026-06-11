<?php

declare(strict_types=1);

namespace YiiPress\Web\LiveReload;

final class SiteBuildRunner
{
    private string $lastOutput;

    public function __construct(
        private string $yiiBinary,
        private string $contentDir,
        private string $outputDir,
    ) {
        $this->lastOutput = '';
    }

    public function build(): bool
    {
        $lock = fopen(sys_get_temp_dir() . '/yiipress-build-' . hash('xxh128', $this->outputDir) . '.lock', 'c');
        if ($lock !== false) {
            flock($lock, LOCK_EX);
        }

        $command = escapeshellarg($this->yiiBinary)
            . ' build'
            . ' --content-dir=' . escapeshellarg($this->contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1';

        try {
            exec($command, $output, $exitCode);
            $this->lastOutput = implode("\n", $output);

            return $exitCode === 0;
        } finally {
            if ($lock !== false) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    public function lastOutput(): string
    {
        return $this->lastOutput;
    }
}

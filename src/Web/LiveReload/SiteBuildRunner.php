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

    public function build(): SiteBuildResult
    {
        $lockId = hash('xxh128', $this->outputDir);
        $lockPath = sys_get_temp_dir() . '/yiipress-build-' . $lockId . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            $this->lastOutput = sprintf('Unable to open build lock "%s" (%s).', $lockPath, $lockId);

            return new SiteBuildResult(1, $this->lastOutput);
        }

        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            $this->lastOutput = sprintf('Unable to acquire build lock "%s" (%s).', $lockPath, $lockId);

            return new SiteBuildResult(1, $this->lastOutput);
        }

        $command = escapeshellarg($this->yiiBinary)
            . ' build'
            . ' --content-dir=' . escapeshellarg($this->contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1';

        try {
            exec($command, $output, $exitCode);
            $this->lastOutput = implode("\n", $output);

            return new SiteBuildResult($exitCode, $this->lastOutput);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function lastOutput(): string
    {
        return $this->lastOutput;
    }
}

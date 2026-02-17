<?php

declare(strict_types=1);

namespace App\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Yii\Console\ExitCode;

use function str_starts_with;

#[AsCommand(
    name: 'clean',
    description: 'Clears build output and caches',
)]
final class CleanCommand extends Command
{
    private const string DEFAULT_OUTPUT_DIR = 'output';
    private const string CACHE_DIR = 'runtime/cache/build';

    public function __construct(private Aliases $aliases)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'output-dir',
            'o',
            InputOption::VALUE_REQUIRED,
            'Path to the output directory',
            self::DEFAULT_OUTPUT_DIR,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rootPath = $this->aliases->get('@root');

        /** @var string $outputDirOption */
        $outputDirOption = $input->getOption('output-dir');
        $outputDir = $this->resolvePath($outputDirOption, $rootPath);
        $cacheDir = $rootPath . '/' . self::CACHE_DIR;

        $outputRemoved = $this->removeDirectory($outputDir);
        $cacheRemoved = $this->removeDirectory($cacheDir);

        if ($outputRemoved) {
            $output->writeln("Removed output directory: <comment>$outputDir</comment>");
        } else {
            $output->writeln("Output directory not found: <comment>$outputDir</comment>");
        }

        if ($cacheRemoved) {
            $output->writeln("Removed cache directory: <comment>$cacheDir</comment>");
        } else {
            $output->writeln("Cache directory not found: <comment>$cacheDir</comment>");
        }

        return ExitCode::OK;
    }

    private function removeDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);

        return true;
    }

    private function resolvePath(string $path, string $rootPath): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $rootPath . '/' . $path;
    }
}

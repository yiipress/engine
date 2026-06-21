<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Build\SiteChecker;
use Yiisoft\Yii\Console\ExitCode;

use function count;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

#[AsCommand(
    name: 'check:links',
    description: 'Checks generated site links and anchors',
)]
final class CheckCommand extends Command
{
    private const string DEFAULT_OUTPUT_DIR = 'output';

    public function __construct(private readonly string $rootPath)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'output-dir',
            'o',
            InputOption::VALUE_REQUIRED,
            'Path to the generated output directory',
            self::DEFAULT_OUTPUT_DIR,
        );
        $this->addOption(
            'external',
            null,
            InputOption::VALUE_NONE,
            'Also validate external HTTP(S) links',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $outputDirOption */
        $outputDirOption = $input->getOption('output-dir');
        $outputDir = $this->resolvePath($outputDirOption, $this->rootPath);

        if (!is_dir($outputDir)) {
            $output->writeln('<error>Output directory not found: ' . OutputFormatter::escape($outputDir) . '</error>');
            return ExitCode::DATAERR;
        }

        $issues = (new SiteChecker())->check($outputDir, (bool) $input->getOption('external'));
        if ($issues === []) {
            $output->writeln('<info>Site check passed.</info>');
            return ExitCode::OK;
        }

        foreach ($issues as $issue) {
            $output->writeln(sprintf(
                '<error>%s: %s "%s"</error>',
                OutputFormatter::escape($this->relativePath($issue->filePath, $outputDir)),
                OutputFormatter::escape($issue->message),
                OutputFormatter::escape($issue->target),
            ));
        }

        $output->writeln('<error>Site check failed: ' . count($issues) . ' issue(s).</error>');

        return ExitCode::DATAERR;
    }

    private function resolvePath(string $path, string $rootPath): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $rootPath . '/' . $path;
    }

    private function relativePath(string $path, string $baseDir): string
    {
        $prefix = $baseDir . '/';

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }
}

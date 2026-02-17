<?php

declare(strict_types=1);

namespace App\Console;

use App\Import\ContentImporterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Yii\Console\ExitCode;

use function str_starts_with;

#[AsCommand(
    name: 'import',
    description: 'Import content from external sources (Telegram, WordPress, etc.)',
)]
final class ImportCommand extends Command
{
    /**
     * @param array<string, ContentImporterInterface> $importers
     */
    public function __construct(
        private string $rootPath,
        private array $importers,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->ignoreValidationErrors();

        $this->addArgument(
            'source',
            InputArgument::REQUIRED,
            'Source type to import from (' . implode(', ', array_keys($this->importers)) . ')',
        );
        $this->addOption(
            'collection',
            'c',
            InputOption::VALUE_REQUIRED,
            'Target collection name',
            'blog',
        );
        $this->addOption(
            'content-dir',
            'd',
            InputOption::VALUE_REQUIRED,
            'Path to the content directory',
            'content',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $source */
        $source = $input->getArgument('source');

        /** @var string $collection */
        $collection = $input->getOption('collection');

        /** @var string $contentDirOption */
        $contentDirOption = $input->getOption('content-dir');
        $contentDir = $this->resolvePath($contentDirOption, $this->rootPath);

        if (!isset($this->importers[$source])) {
            $available = $this->importers !== [] ? implode(', ', array_keys($this->importers)) : 'none';
            $output->writeln("<error>Unknown source type \"$source\". Available: $available</error>");
            $this->printImporterOptions($output);
            return ExitCode::DATAERR;
        }

        $importer = $this->importers[$source];
        $rawOptions = $this->parseRawOptions();
        $options = $this->resolveImporterOptions($importer, $rawOptions, $output);
        if ($options === null) {
            return ExitCode::DATAERR;
        }

        $output->writeln("<info>Importing from $source...</info>");
        foreach ($options as $name => $value) {
            if ($value !== null) {
                $output->writeln("  $name: <comment>$value</comment>");
            }
        }
        $output->writeln("  Target: <comment>$contentDir/$collection</comment>");

        $result = $importer->import($options, $contentDir, $collection);

        foreach ($result->warnings() as $warning) {
            $output->writeln("  <comment>⚠ $warning</comment>");
        }

        if ($result->skippedFiles() !== []) {
            $output->writeln('  Skipped: <comment>' . count($result->skippedFiles()) . '</comment>');
        }

        $output->writeln("  Total messages: <comment>{$result->totalMessages()}</comment>");
        $output->writeln("  Imported: <comment>{$result->importedCount()}</comment>");
        $output->writeln('<info>Import complete.</info>');

        return ExitCode::OK;
    }

    /**
     * @return array<string, string|null>
     */
    private function parseRawOptions(): array
    {
        $argv = $_SERVER['argv'] ?? [];
        $options = [];

        foreach ($argv as $token) {
            if (!str_starts_with($token, '--')) {
                continue;
            }

            $token = substr($token, 2);
            $equalsPos = strpos($token, '=');
            if ($equalsPos !== false) {
                $options[substr($token, 0, $equalsPos)] = substr($token, $equalsPos + 1);
            } else {
                $options[$token] = null;
            }
        }

        return $options;
    }

    /**
     * @param array<string, string|null> $rawOptions
     * @return array<string, string|null>|null null if validation fails
     */
    private function resolveImporterOptions(
        ContentImporterInterface $importer,
        array $rawOptions,
        OutputInterface $output,
    ): ?array {
        $options = [];
        foreach ($importer->options() as $option) {
            $value = $rawOptions[$option->name] ?? $option->default;

            if ($value !== null && $value !== '') {
                $value = $this->resolvePath($value, $this->rootPath);
            }

            if ($option->required && ($value === null || $value === '')) {
                $output->writeln("<error>Missing required option --{$option->name} for {$importer->name()} importer</error>");
                $this->printImporterUsage($importer, $output);
                return null;
            }

            $options[$option->name] = $value;
        }

        return $options;
    }

    private function printImporterUsage(ContentImporterInterface $importer, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln("<info>Options for {$importer->name()} importer:</info>");
        foreach ($importer->options() as $option) {
            $required = $option->required ? ' <comment>(required)</comment>' : '';
            $default = $option->default !== null ? " [default: {$option->default}]" : '';
            $output->writeln("  --{$option->name}  {$option->description}{$required}{$default}");
        }
    }

    private function printImporterOptions(OutputInterface $output): void
    {
        $output->writeln('');
        foreach ($this->importers as $name => $importer) {
            $output->writeln("<info>$name</info> options:");
            foreach ($importer->options() as $option) {
                $required = $option->required ? ' (required)' : '';
                $output->writeln("  --{$option->name}  {$option->description}{$required}");
            }
        }
    }

    private function resolvePath(string $path, string $rootPath): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $rootPath . '/' . $path;
    }
}

<?php

declare(strict_types=1);

namespace App\Console;

use App\Content\Parser\CollectionConfigParser;
use App\Content\Parser\SiteConfigParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Yii\Console\ExitCode;

use function str_starts_with;

#[AsCommand(
    name: 'new',
    description: 'Scaffold a new content entry or standalone page',
)]
final class NewCommand extends Command
{
    public function __construct(private readonly string $rootPath)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'title',
            InputArgument::REQUIRED,
            'Title of the new entry',
        );
        $this->addOption(
            'collection',
            'c',
            InputOption::VALUE_REQUIRED,
            'Collection to create the entry in (omit for standalone page)',
        );
        $this->addOption(
            'content-dir',
            'd',
            InputOption::VALUE_REQUIRED,
            'Path to the content directory',
            'content',
        );
        $this->addOption(
            'draft',
            null,
            InputOption::VALUE_NONE,
            'Mark the entry as a draft',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rootPath = $this->rootPath;

        /** @var string $title */
        $title = $input->getArgument('title');

        /** @var string $contentDirOption */
        $contentDirOption = $input->getOption('content-dir');
        $contentDir = $this->resolvePath($contentDirOption, $rootPath);

        /** @var string|null $collectionName */
        $collectionName = $input->getOption('collection');
        $draft = (bool) $input->getOption('draft');

        if (!is_dir($contentDir)) {
            $output->writeln("<error>Content directory not found: $contentDir</error>");
            return ExitCode::DATAERR;
        }

        $slug = $this->slugify($title);

        if ($collectionName !== null) {
            return $this->createCollectionEntry($contentDir, $collectionName, $title, $slug, $draft, $output);
        }

        return $this->createStandalonePage($contentDir, $title, $slug, $draft, $output);
    }

    private function createCollectionEntry(
        string $contentDir,
        string $collectionName,
        string $title,
        string $slug,
        bool $draft,
        OutputInterface $output,
    ): int {
        $collectionDir = $contentDir . '/' . $collectionName;
        $configPath = $collectionDir . '/_collection.yaml';

        if (!is_file($configPath)) {
            $output->writeln("<error>Collection \"$collectionName\" not found (no _collection.yaml)</error>");
            return ExitCode::DATAERR;
        }

        $collection = new CollectionConfigParser()->parse($configPath, $collectionName);

        $filename = $slug . '.md';
        if ($collection->sortBy === 'date') {
            $filename = date('Y-m-d') . '-' . $filename;
        }

        $filePath = $collectionDir . '/' . $filename;

        if (is_file($filePath)) {
            $output->writeln("<error>File already exists: $filePath</error>");
            return ExitCode::DATAERR;
        }

        $defaultAuthor = $this->getDefaultAuthor($contentDir);

        $frontMatter = "---\ntitle: " . $this->yamlEscape($title) . "\n";
        if ($draft) {
            $frontMatter .= "draft: true\n";
        }
        if ($defaultAuthor !== '') {
            $frontMatter .= "authors:\n  - $defaultAuthor\n";
        }
        $frontMatter .= "---\n\n";

        $dir = \dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($filePath, $frontMatter);

        $output->writeln("Created: <info>$filePath</info>");
        return ExitCode::OK;
    }

    private function createStandalonePage(
        string $contentDir,
        string $title,
        string $slug,
        bool $draft,
        OutputInterface $output,
    ): int {
        $filePath = $contentDir . '/' . $slug . '.md';

        if (is_file($filePath)) {
            $output->writeln("<error>File already exists: $filePath</error>");
            return ExitCode::DATAERR;
        }

        $frontMatter = "---\ntitle: " . $this->yamlEscape($title) . "\npermalink: /$slug/\n";
        if ($draft) {
            $frontMatter .= "draft: true\n";
        }
        $frontMatter .= "---\n\n";

        file_put_contents($filePath, $frontMatter);

        $output->writeln("Created: <info>$filePath</info>");
        return ExitCode::OK;
    }

    private function slugify(string $title): string
    {
        $slug = mb_strtolower($title);
        $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
    }

    private function yamlEscape(string $value): string
    {
        if (preg_match('/[:#\[\]{}|>&*!,\'"%@`]/', $value) === 1) {
            return '"' . addcslashes($value, '"\\') . '"';
        }

        return $value;
    }

    private function getDefaultAuthor(string $contentDir): string
    {
        $configPath = $contentDir . '/config.yaml';
        if (!is_file($configPath)) {
            return '';
        }

        return new SiteConfigParser()->parse($configPath)->defaultAuthor;
    }

    private function resolvePath(string $path, string $rootPath): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $rootPath . '/' . $path;
    }
}

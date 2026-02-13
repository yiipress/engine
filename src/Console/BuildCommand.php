<?php

declare(strict_types=1);

namespace App\Console;

use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use App\Content\Parser\ContentParser;
use App\Render\MarkdownRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Yii\Console\ExitCode;

use function str_starts_with;

#[AsCommand(
    name: 'build',
    description: 'Generates static HTML content from source files',
)]
final class BuildCommand extends Command
{
    private const string ENTRY_TEMPLATE = __DIR__ . '/../Render/Template/entry.php';

    public function __construct(
        private Aliases $aliases,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'content-dir',
            'c',
            InputOption::VALUE_REQUIRED,
            'Path to the content directory',
            'content',
        );
        $this->addOption(
            'output-dir',
            'o',
            InputOption::VALUE_REQUIRED,
            'Path to the output directory',
            'output',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rootPath = $this->aliases->get('@root');

        /** @var string $contentDirOption */
        $contentDirOption = $input->getOption('content-dir');
        $contentDir = $this->resolvePath($contentDirOption, $rootPath);

        /** @var string $outputDirOption */
        $outputDirOption = $input->getOption('output-dir');
        $outputDir = $this->resolvePath($outputDirOption, $rootPath);

        if (!is_dir($contentDir)) {
            $output->writeln("<error>Content directory not found: $contentDir</error>");
            return ExitCode::DATAERR;
        }

        $output->writeln('<info>Parsing content...</info>');

        $parser = new ContentParser();
        $siteConfig = $parser->parseSiteConfig($contentDir);
        $navigation = $parser->parseNavigation($contentDir);
        $collections = $parser->parseCollections($contentDir);
        $authors = iterator_to_array($parser->parseAuthors($contentDir));

        $output->writeln("  Site: <comment>{$siteConfig->title}</comment>");
        $output->writeln('  Collections: <comment>' . count($collections) . '</comment>');
        $output->writeln('  Authors: <comment>' . count($authors) . '</comment>');
        $output->writeln('  Menus: <comment>' . count($navigation->menuNames()) . '</comment>');

        $output->writeln('<info>Rendering and writing output...</info>');

        $this->prepareOutputDir($outputDir);

        $renderer = new MarkdownRenderer();
        $entryCount = 0;
        foreach ($collections as $collectionName => $collection) {
            foreach ($parser->parseEntries($contentDir, $collectionName) as $entry) {
                $permalink = $entry->permalink !== ''
                    ? $entry->permalink
                    : $this->resolvePermalink($collection->permalink, $collectionName, $entry->slug);

                $filePath = $outputDir . $permalink . 'index.html';
                $dirPath = dirname($filePath);

                if (!is_dir($dirPath)) {
                    mkdir($dirPath, 0o755, true);
                }

                $html = $renderer->render($entry->body());
                file_put_contents($filePath, $this->renderTemplate($siteConfig, $entry, $html));
                $entryCount++;
            }
        }

        $output->writeln("  Entries written: <comment>$entryCount</comment>");
        $output->writeln('<info>Build complete.</info>');

        return ExitCode::OK;
    }

    private function prepareOutputDir(string $outputDir): void
    {
        if (is_dir($outputDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($outputDir, \FilesystemIterator::SKIP_DOTS),
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
        } else {
            mkdir($outputDir, 0o755, true);
        }
    }

    private function resolvePath(string $path, string $rootPath): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $rootPath . '/' . $path;
    }

    private function renderTemplate(SiteConfig $siteConfig, Entry $entry, string $content): string
    {
        $siteTitle = $siteConfig->title;
        $entryTitle = $entry->title;
        $date = $entry->date?->format('Y-m-d') ?? '';
        $author = implode(', ', $entry->authors);
        $collection = $entry->collection;

        ob_start();
        require self::ENTRY_TEMPLATE;
        return ob_get_clean();
    }

    private function resolvePermalink(string $pattern, string $collection, string $slug): string
    {
        return str_replace(
            [':collection', ':slug'],
            [$collection, $slug],
            $pattern,
        );
    }
}

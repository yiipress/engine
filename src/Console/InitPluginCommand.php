<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Build\FileWriter;
use YiiPress\Content\Slugifier;
use Yiisoft\Files\FileHelper;
use Yiisoft\Yii\Console\ExitCode;

use function dirname;
use function is_dir;
use function is_file;
use function preg_match;
use function str_starts_with;

#[AsCommand(
    name: 'init:plugin',
    description: 'Initialize a project content processor plugin',
)]
final class InitPluginCommand extends Command
{
    public function __construct(private readonly string $rootPath)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Plugin name');
        $this->addOption(
            'content-dir',
            'c',
            InputOption::VALUE_REQUIRED,
            'Path to the content directory',
            'content',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $name */
        $name = $input->getArgument('name');
        /** @var string $contentDirOption */
        $contentDirOption = $input->getOption('content-dir');
        $contentDir = $this->resolvePath($contentDirOption);

        if (!is_dir($contentDir)) {
            $output->writeln("<error>Content directory not found: $contentDir</error>");
            return ExitCode::DATAERR;
        }

        $slug = Slugifier::slugify($name);
        if ($slug === '') {
            $output->writeln('<error>Plugin name must contain at least one letter or number.</error>');
            return ExitCode::DATAERR;
        }

        $filePath = $contentDir . '/processors/' . $slug . '.php';
        if (is_file($filePath)) {
            $output->writeln("<error>Path already exists: $filePath</error>");
            return ExitCode::DATAERR;
        }

        FileHelper::ensureDirectory(dirname($filePath), 0o755);
        FileWriter::write($filePath, $this->plugin());
        $output->writeln("Created: <info>$filePath</info>");

        return ExitCode::OK;
    }

    private function plugin(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use YiiPress\Content\Model\Entry;

return static function (string $content, Entry $entry): string {
    return $content;
};

PHP;
    }

    private function resolvePath(string $path): string
    {
        if (
            str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/D', $path) === 1
        ) {
            return $path;
        }

        return $this->rootPath . '/' . $path;
    }
}

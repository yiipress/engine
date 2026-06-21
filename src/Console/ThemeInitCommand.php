<?php

declare(strict_types=1);

namespace YiiPress\Console;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Build\ThemeInitializer;
use YiiPress\Build\ThemeRegistry;
use Yiisoft\Yii\Console\ExitCode;

use function basename;
use function file_get_contents;
use function file_put_contents;
use function is_file;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function str_replace;

#[AsCommand(
    name: 'theme:init',
    description: 'Initializes editable theme files in the project',
)]
final class ThemeInitCommand extends Command
{
    private const string DEFAULT_CONTENT_DIR = 'content';
    private const string DEFAULT_TARGET_DIR = 'themes/custom';
    private const string DEFAULT_THEME = 'minimal';

    public function __construct(
        private readonly string $rootPath,
        private readonly ThemeRegistry $themeRegistry,
        private readonly ThemeInitializer $themeInitializer = new ThemeInitializer(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'target-dir',
            InputArgument::OPTIONAL,
            'Directory to initialize theme files in',
            self::DEFAULT_TARGET_DIR,
        );
        $this->addOption(
            'theme',
            't',
            InputOption::VALUE_REQUIRED,
            'Bundled theme name to use as the source',
            self::DEFAULT_THEME,
        );
        $this->addOption(
            'content-dir',
            'c',
            InputOption::VALUE_REQUIRED,
            'Path to the content directory containing config.yaml',
            self::DEFAULT_CONTENT_DIR,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $targetDirArgument */
        $targetDirArgument = $input->getArgument('target-dir');
        $targetDir = $this->resolvePath($targetDirArgument, $this->rootPath);
        $projectThemeName = basename(str_replace('\\', '/', $targetDir));

        /** @var string $themeName */
        $themeName = $input->getOption('theme');

        /** @var string $contentDirOption */
        $contentDirOption = $input->getOption('content-dir');
        $configPath = $this->resolvePath($contentDirOption, $this->rootPath) . '/config.yaml';

        try {
            if (!is_file($configPath)) {
                throw new RuntimeException(sprintf('Content config file "%s" was not found.', $configPath));
            }

            if (!$this->isValidProjectThemeName($projectThemeName)) {
                throw new RuntimeException(sprintf(
                    'Target directory name "%s" is not a valid project theme name.',
                    $projectThemeName,
                ));
            }

            $theme = $this->themeRegistry->get($themeName);
            $copied = $this->themeInitializer->initialize($theme, $targetDir);
            $this->configureTheme($configPath, $projectThemeName);
        } catch (RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return ExitCode::DATAERR;
        }

        $output->writeln(sprintf(
            'Copied <info>%d</info> theme files from <comment>%s</comment> to <info>%s</info>.',
            $copied,
            $themeName,
            $targetDir,
        ));
        $output->writeln(sprintf('Configured <info>%s</info> to use theme <comment>%s</comment>.', $configPath, $projectThemeName));

        return ExitCode::OK;
    }

    private function configureTheme(string $configPath, string $themeName): void
    {
        $config = file_get_contents($configPath);
        if ($config === false) {
            throw new RuntimeException(sprintf('Unable to read content config file "%s".', $configPath));
        }

        $themeLine = sprintf('theme: "%s"', $themeName);
        if (preg_match('/^theme:\s*.*$/m', $config) === 1) {
            $updatedConfig = preg_replace('/^theme:\s*.*$/m', $themeLine, $config, 1);
            if ($updatedConfig === null) {
                throw new RuntimeException(sprintf('Unable to update content config file "%s".', $configPath));
            }
        } else {
            $updatedConfig = $config;
            if ($updatedConfig !== '' && !str_ends_with($updatedConfig, "\n")) {
                $updatedConfig .= "\n";
            }

            $updatedConfig .= $themeLine . "\n";
        }

        if (file_put_contents($configPath, $updatedConfig) === false) {
            throw new RuntimeException(sprintf('Unable to write content config file "%s".', $configPath));
        }
    }

    private function isValidProjectThemeName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/D', $name) === 1;
    }

    private function resolvePath(string $path, string $rootPath): string
    {
        if (
            str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/D', $path) === 1
        ) {
            return $path;
        }

        return $rootPath . '/' . $path;
    }
}

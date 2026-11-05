<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Http\HttpClientInterface;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Install\InstallRequest;
use YiiPress\Extension\Install\InstallResult;
use YiiPress\Extension\Install\PackageInstaller;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Processor\EnablementSnippet;
use YiiPress\Extension\Processor\ProcessorDestinationPolicy;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\GitHubRepository;
use Yiisoft\Yii\Console\ExitCode;

use function is_string;
use function sprintf;

#[AsCommand(
    name: 'get:processor',
    description: 'Installs a content processor from the releases of its GitHub repository; it stays disabled',
)]
final class GetProcessorCommand extends Command
{
    private readonly EngineVersion $engine;
    private readonly Platform $platform;

    /**
     * @param EngineVersion|null $engine This YiiPress; {@see EngineVersion::current()} when `null`.
     * @param Platform|null $platform This machine; {@see Platform::current()} when `null`.
     */
    public function __construct(
        private readonly string $rootPath,
        private readonly HttpClientInterface $http,
        ?EngineVersion $engine = null,
        ?Platform $platform = null,
    ) {
        $this->engine = $engine ?? EngineVersion::current();
        $this->platform = $platform ?? Platform::current();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'url',
                null,
                InputOption::VALUE_REQUIRED,
                'Processor repository, https://github.com/<owner>/<repo>',
            )
            ->addOption('version', null, InputOption::VALUE_REQUIRED, 'Release X.Y.Z; the newest compatible if omitted')
            ->addOption('content-dir', null, InputOption::VALUE_REQUIRED, 'Directory of config.yaml', 'content')
            ->addUsage('--url=https://github.com/yiipress/processor-toc')
            ->addUsage('--url=https://github.com/yiipress/processor-toc --version=1.2.0');
        EngineVersionOption::add($this, 'releases');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            /** @var string $contentDir */
            $contentDir = $input->getOption('content-dir');
            $result = new PackageInstaller(new GitHubReleaseSource($this->http))->install(
                $this->request($input),
                new ProcessorDestinationPolicy($contentDir),
            );
        } catch (InstallException $exception) {
            return ExtensionIdArgument::fail($output, $exception);
        }

        $this->report($output, $result);

        return ExitCode::OK;
    }

    private function request(InputInterface $input): InstallRequest
    {
        $url = $input->getOption('url');
        if (!is_string($url) || $url === '') {
            throw new InstallException(
                'source_invalid',
                'Pass the GitHub repository of the processor with --url=https://github.com/<owner>/<repo>.',
                ExitCode::USAGE,
            );
        }
        $version = $input->getOption('version');

        return new InstallRequest(
            $this->rootPath,
            GitHubRepository::fromUrl($url),
            ExtensionType::Processor,
            is_string($version) ? $version : null,
            EngineVersionOption::resolve($this->engine, $input->getOption(EngineVersionOption::NAME)),
            $this->platform,
        );
    }

    private function report(OutputInterface $output, InstallResult $result): void
    {
        if ($result->recovered !== null) {
            $output->writeln($result->recovered);
        }
        $manifest = $result->selected->manifest;
        $output->writeln(sprintf(
            'Selected %s %s (%s), %s.',
            $manifest->name,
            $result->selected->release->version,
            $result->selected->release->tag,
            $result->selected->reason,
        ));
        $output->writeln(sprintf('Installed %s into %s.', $manifest->id, $result->locked->destination));
        $processor = $manifest->processor();
        if ($processor !== null) {
            $output->writeln(new EnablementSnippet()->forInstalled($this->rootPath, $result->locked, $processor));
        }
    }
}

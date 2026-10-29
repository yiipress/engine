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
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\GitHubRepository;
use YiiPress\Extension\SemanticVersion;
use YiiPress\Extension\Theme\ThemeDestinationPolicy;
use Yiisoft\Yii\Console\ExitCode;

use function is_string;
use function sprintf;

#[AsCommand(
    name: 'get:theme',
    description: 'Installs a theme from the releases of its GitHub repository',
)]
final class GetThemeCommand extends Command
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
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'GitHub repository of the theme, https://github.com/<owner>/<repo>')
            ->addOption('version', null, InputOption::VALUE_REQUIRED, 'Release to install, X.Y.Z without "v"; the highest compatible one when omitted')
            ->addOption('no-activate', null, InputOption::VALUE_NONE, 'Install without setting "theme" in config.yaml')
            ->addOption('content-dir', null, InputOption::VALUE_REQUIRED, 'Directory of config.yaml', 'content')
            ->addOption('engine-version', null, InputOption::VALUE_REQUIRED, 'YiiPress version to check compatibility against; only in a source checkout, where the version is unknown')
            ->addUsage('--url=https://github.com/yiipress/theme-wonder')
            ->addUsage('--url=https://github.com/yiipress/theme-wonder --version=1.2.0 --no-activate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $request = $this->request($input);
            /** @var string $contentDir */
            $contentDir = $input->getOption('content-dir');
            $policy = new ThemeDestinationPolicy($contentDir, !$input->getOption('no-activate'));
            $result = new PackageInstaller(new GitHubReleaseSource($this->http))->install($request, $policy);
        } catch (InstallException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
            $solution = $exception->getSolution();
            if ($solution !== null) {
                $output->writeln($solution);
            }

            return $exception->getCode();
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
                'Pass the GitHub repository of the theme with --url=https://github.com/<owner>/<repo>.',
                ExitCode::USAGE,
            );
        }
        $version = $input->getOption('version');

        return new InstallRequest(
            $this->rootPath,
            GitHubRepository::fromUrl($url),
            ExtensionType::Theme,
            is_string($version) ? $version : null,
            $this->engineVersion($input->getOption('engine-version')),
            $this->platform,
        );
    }

    private function engineVersion(mixed $override): EngineVersion
    {
        if (!is_string($override)) {
            return $this->engine;
        }
        if ($this->engine->version !== null) {
            throw new InstallException('engine_version_unknown', sprintf(
                '--engine-version can only be used when the YiiPress version is unknown; this is YiiPress %s.',
                $this->engine->describe(),
            ), ExitCode::USAGE);
        }
        if (!SemanticVersion::isValid($override)) {
            throw new InstallException('engine_version_unknown', sprintf(
                '--engine-version="%s" is not a version in the form X.Y.Z.',
                $override,
            ), ExitCode::USAGE);
        }

        return EngineVersion::fromBuild($override);
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
        $output->writeln($result->activated === null
            ? sprintf('Not activated. Set "theme: %s" in config.yaml to use it.', $result->locked->name)
            : sprintf('Activated: "theme" is now "%s" in %s.', $result->locked->name, $result->activated));
    }
}

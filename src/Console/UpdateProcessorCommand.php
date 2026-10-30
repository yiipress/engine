<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\Http\HttpClientInterface;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lifecycle\ExtensionUpdater;
use YiiPress\Extension\Lifecycle\UpdateResult;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubReleaseSource;
use Yiisoft\Yii\Console\ExitCode;

use function is_string;
use function sprintf;

#[AsCommand(
    name: 'update:processor',
    description: 'Updates an installed content processor to the newest compatible release, or to the given version',
)]
final class UpdateProcessorCommand extends Command
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
            ->addArgument('id', InputArgument::REQUIRED, 'Processor ID from yiipress.lock, for example acme/toc')
            ->addOption('version', null, InputOption::VALUE_REQUIRED, 'Exact version X.Y.Z; may be older')
            ->addUsage('acme/toc')
            ->addUsage('acme/toc --version=1.2.0');
        EngineVersionOption::add($this, 'releases');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $version = $input->getOption('version');
            $result = ExtensionUpdater::forProcessor(new GitHubReleaseSource($this->http))->update(
                $this->rootPath,
                ExtensionIdArgument::resolve($input),
                is_string($version) ? $version : null,
                EngineVersionOption::resolve($this->engine, $input->getOption(EngineVersionOption::NAME)),
                $this->platform,
            );
        } catch (InstallException $exception) {
            return ExtensionIdArgument::fail($output, $exception);
        }

        $this->report($output, $result, $input->getOption('version') !== null);

        return ExitCode::OK;
    }

    private function report(OutputInterface $output, UpdateResult $result, bool $exact): void
    {
        if ($result->recovered !== null) {
            $output->writeln($result->recovered);
        }
        $id = $result->selected->manifest->id;
        if (!$result->updated()) {
            $output->writeln($exact
                ? sprintf('%s is already at %s.', $id, $result->previous->version)
                : sprintf(
                    '%s %s is up to date: the newest compatible release is %s.',
                    $id,
                    $result->previous->version,
                    $result->selected->release->version,
                ));

            return;
        }
        $output->writeln(sprintf(
            'Updated %s from %s to %s (%s, %s) in %s.',
            $id,
            $result->previous->version,
            $result->current->version,
            $result->current->tag,
            $result->selected->reason,
            $result->current->destination,
        ));
    }
}

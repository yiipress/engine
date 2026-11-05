<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\Http\HttpClientInterface;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Restore\PackageRestorer;
use YiiPress\Extension\Restore\RestoredPackage;
use YiiPress\Extension\Restore\RestoreRefusedException;
use YiiPress\Extension\Restore\RestoreResult;
use Yiisoft\Yii\Console\ExitCode;

use function count;
use function implode;
use function sprintf;

#[AsCommand(
    name: 'restore:extensions',
    description: 'Installs the extensions of yiipress.lock exactly as locked, for example in a fresh checkout',
)]
final class RestoreExtensionsCommand extends Command
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
        EngineVersionOption::add($this, 'locked packages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = new PackageRestorer(new GitHubReleaseSource($this->http))->restore(
                $this->rootPath,
                EngineVersionOption::resolve($this->engine, $input->getOption(EngineVersionOption::NAME)),
                $this->platform,
            );
        } catch (RestoreRefusedException $exception) {
            $output->writeln(sprintf(
                '<error>Nothing was restored: %s found; no file was changed.</error>',
                count($exception->problems) === 1 ? 'a problem' : count($exception->problems) . ' problems',
            ));
            foreach ($exception->problems as $problem) {
                $output->writeln(' - ' . $problem->getMessage());
                if ($problem->getSolution() !== null) {
                    $output->writeln('   ' . $problem->getSolution());
                }
            }

            return $exception->getCode();
        } catch (InstallException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
            if ($exception->getSolution() !== null) {
                $output->writeln($exception->getSolution());
            }

            return $exception->getCode();
        }

        $this->report($output, $result);

        return ExitCode::OK;
    }

    private function report(OutputInterface $output, RestoreResult $result): void
    {
        if ($result->recovered !== null) {
            $output->writeln($result->recovered);
        }
        if ($result->packages === []) {
            $output->writeln('yiipress.lock lists no extensions; nothing to restore.');
        }
        foreach ($result->packages as $package) {
            if ($package->warning !== null) {
                $output->writeln(sprintf('<comment>Warning: %s</comment>', $package->warning));
            }
            $output->writeln(sprintf('%s: %s', $package->id, match ($package->status) {
                RestoredPackage::INTACT => sprintf('matches yiipress.lock in %s.', $package->destination),
                RestoredPackage::INSTALLED => sprintf(
                    'installed into %s from the locked archive.',
                    $package->destination,
                ),
                default => sprintf('put back %s in %s.', implode(', ', $package->files), $package->destination),
            }));
        }
    }
}

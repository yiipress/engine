<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Input\ArgvInput;
use Throwable;
use Yiisoft\Yii\Console\Application;
use Yiisoft\Yii\Console\ExitCode;
use Yiisoft\Yii\Console\Output\ConsoleBufferedOutput;
use Yiisoft\Yii\Runner\ApplicationRunner as BaseApplicationRunner;

final class ApplicationRunner extends BaseApplicationRunner
{
    /**
     * @param list<string> $nestedParamsGroups
     * @param list<string> $nestedEventsGroups
     * @param list<object> $configModifiers
     */
    public function __construct(
        string $rootPath,
        bool $debug = false,
        bool $checkEvents = false,
        ?string $environment = null,
        string $bootstrapGroup = 'bootstrap-console',
        string $eventsGroup = 'events-console',
        string $diGroup = 'di-console',
        string $diProvidersGroup = 'di-providers-console',
        string $diDelegatesGroup = 'di-delegates-console',
        string $diTagsGroup = 'di-tags-console',
        string $paramsGroup = 'params-console',
        array $nestedParamsGroups = ['params'],
        array $nestedEventsGroups = ['events'],
        array $configModifiers = [],
        string $configDirectory = 'config',
        string $vendorDirectory = 'vendor',
        string $configMergePlanFile = '.merge-plan.php',
    ) {
        parent::__construct(
            $rootPath,
            $debug,
            $checkEvents,
            $environment,
            $bootstrapGroup,
            $eventsGroup,
            $diGroup,
            $diProvidersGroup,
            $diDelegatesGroup,
            $diTagsGroup,
            $paramsGroup,
            $nestedParamsGroups,
            $nestedEventsGroups,
            $configModifiers,
            $configDirectory,
            $vendorDirectory,
            $configMergePlanFile,
        );
    }

    public function run(): void
    {
        $this->runBootstrap();
        $this->checkEvents();

        /** @var Application $application */
        $application = $this->getContainer()->get(Application::class);
        $application->setCatchExceptions(false);

        $exitCode = ExitCode::UNSPECIFIED_ERROR;
        $input = new ArgvInput();
        $output = new ConsoleBufferedOutput();

        try {
            $application->start($input);
            $exitCode = $application->run($input, $output);
        } catch (Throwable $throwable) {
            (new ExceptionRenderer())->render($throwable, $output);
        } finally {
            $application->shutdown($exitCode);
            exit($exitCode);
        }
    }
}

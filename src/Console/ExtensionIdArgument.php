<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\Install\InstallException;
use Yiisoft\Yii\Console\ExitCode;

use function is_string;
use function sprintf;

/**
 * The `id` argument of the commands that change one installed extension, and how they report a failure.
 */
final class ExtensionIdArgument
{
    /**
     * @throws InstallException `id_invalid` with the usage exit code.
     */
    public static function resolve(InputInterface $input): ExtensionId
    {
        $value = $input->getArgument('id');
        $id = is_string($value) ? ExtensionId::tryFromString($value) : null;
        if ($id === null) {
            throw new InstallException('id_invalid', sprintf(
                '"%s" is not an extension ID; pass it as vendor/name, as yiipress.lock lists it.',
                is_string($value) ? $value : '',
            ), ExitCode::USAGE);
        }

        return $id;
    }

    /**
     * Prints the problem and its solution.
     *
     * @return int The exit code.
     */
    public static function fail(OutputInterface $output, InstallException $exception): int
    {
        $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
        if ($exception->getSolution() !== null) {
            $output->writeln($exception->getSolution());
        }

        return $exception->getCode();
    }
}

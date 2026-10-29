<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\SemanticVersion;
use Yiisoft\Yii\Console\ExitCode;

use function is_string;
use function sprintf;

/**
 * `--engine-version=X.Y.Z` of the extension commands: the YiiPress version to check packages against, accepted
 * only when this YiiPress does not know its own version (source checkouts, the dev image).
 */
final class EngineVersionOption
{
    public const string NAME = 'engine-version';

    public static function add(Command $command, string $what): void
    {
        $command->addOption(
            self::NAME,
            null,
            InputOption::VALUE_REQUIRED,
            sprintf('YiiPress version to check %s against; only when this YiiPress does not know its version', $what),
        );
    }

    /**
     * @throws InstallException `engine_version_unknown` with the usage exit code.
     */
    public static function resolve(EngineVersion $engine, mixed $override): EngineVersion
    {
        if (!is_string($override)) {
            return $engine;
        }
        if ($engine->version !== null) {
            throw new InstallException('engine_version_unknown', sprintf(
                '--engine-version can only be used when the YiiPress version is unknown; this is YiiPress %s.',
                $engine->describe(),
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
}

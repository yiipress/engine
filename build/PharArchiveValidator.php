<?php

declare(strict_types=1);

namespace YiiPress\Build;

use Phar;
use PharData;
use RecursiveIteratorIterator;
use RuntimeException;

use function strlen;
use function str_replace;
use function str_starts_with;
use function substr;

final class PharArchiveValidator
{
    /** @var list<string> */
    public const array REQUIRED_PATHS = [
        'config/environments/dev/params.php',
        'config/environments/test/params.php',
        'config/web/di/application.php',
        'vendor/symfony/console/Resources/bin/hiddeninput.exe',
        'vendor/symfony/console/Resources/completion.bash',
        'vendor/yiisoft/config/src/Composer/Options.php',
        'vendor/yiisoft/error-handler/templates/development.php',
        'vendor/yiisoft/yii-console/src/Command/Game.php',
        'vendor/yiisoft/yii-console/src/Command/Serve.php',
    ];

    /**
     * @param list<string> $requiredPaths
     */
    public static function validate(Phar|PharData $phar, array $requiredPaths = self::REQUIRED_PATHS): void
    {
        $archivePrefix = 'phar://' . str_replace('\\', '/', $phar->getPath()) . '/';
        foreach (new RecursiveIteratorIterator($phar) as $file) {
            $archivePath = str_replace('\\', '/', $file->getPathname());
            if (!str_starts_with($archivePath, $archivePrefix)) {
                throw new RuntimeException("Could not resolve PHAR entry path: {$archivePath}");
            }

            $archivePath = substr($archivePath, strlen($archivePrefix));
            if (PharArchiveFilter::shouldExclude($archivePath)) {
                throw new RuntimeException("Excluded file was added to the PHAR: {$archivePath}");
            }
        }

        foreach ($requiredPaths as $requiredPath) {
            if (!isset($phar[$requiredPath])) {
                throw new RuntimeException("Required runtime file is missing from the PHAR: {$requiredPath}");
            }
        }
    }
}

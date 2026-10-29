<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Build\ProjectThemeDiscovery;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Theme\ManagedThemes;
use YiiPress\Extension\Theme\UsedThemes;
use YiiPress\Extension\Transaction\Recovery;
use YiiPress\RuntimePaths;
use Yiisoft\Yii\Console\ExitCode;

use function array_diff;
use function array_filter;
use function array_values;
use function hash;
use function in_array;
use function is_dir;
use function sprintf;

/**
 * What `build`, `serve` and the build workers need to know about installed extensions.
 */
final class ExtensionState
{
    /**
     * Exit code that stops `build` or `serve` while an extension operation is interrupted, `null` to go on.
     */
    public static function stop(string $rootPath, OutputInterface $output): ?int
    {
        if (!Recovery::pending($rootPath)) {
            return null;
        }
        $output->writeln('<error>' . Recovery::INTERRUPTED_MESSAGE . '</error>');

        return ExitCode::TEMPFAIL;
    }

    /**
     * Registers the project themes from `themes/` and the `local` templates of the content directory.
     *
     * A theme installed by `get:theme` is registered as managed, and with a site config only when the site or an
     * entry uses it: an installed theme that is not used adds neither templates nor assets to the site.
     *
     * @param SiteConfig|null $siteConfig `null` registers every installed theme; workers render what the build chose.
     * @param OutputInterface|null $output Receives a warning when `yiipress.lock` cannot be read.
     */
    public static function registerThemes(
        ThemeRegistry $registry,
        string $rootPath,
        string $contentDir,
        ?SiteConfig $siteConfig = null,
        ?OutputInterface $output = null,
    ): void {
        try {
            $managed = ManagedThemes::directoryNames($rootPath);
        } catch (InstallException $exception) {
            $managed = [];
            $output?->writeln(sprintf(
                '<comment>Warning: %s Installed themes are used like any other project theme.</comment>',
                $exception->getMessage(),
            ));
        }
        $used = $siteConfig === null ? $managed : self::usedThemes($managed, $rootPath, $contentDir, $siteConfig);

        foreach (new ProjectThemeDiscovery()->discover($rootPath . '/themes', $managed) as $theme) {
            if (!$registry->has($theme->name) && (!$theme->managed || in_array($theme->name, $used, true))) {
                $registry->register($theme);
            }
        }
        if (is_dir($contentDir . '/templates')) {
            $registry->register(new Theme('local', $contentDir . '/templates'));
        }
    }

    /**
     * @param list<string> $managed
     * @return list<string> Installed themes that the site or one of its entries selects.
     */
    private static function usedThemes(
        array $managed,
        string $rootPath,
        string $contentDir,
        SiteConfig $siteConfig,
    ): array {
        $unused = array_values(array_diff($managed, [$siteConfig->theme]));
        if ($unused === []) {
            return $managed;
        }
        $cacheFile = RuntimePaths::cachePath($rootPath) . '/used-themes-' . hash('xxh128', $contentDir) . '.json';
        $selected = new UsedThemes($cacheFile)->find($unused, $contentDir);

        return array_values(array_filter(
            $managed,
            static fn(string $name): bool => !in_array($name, $unused, true) || in_array($name, $selected, true),
        ));
    }
}

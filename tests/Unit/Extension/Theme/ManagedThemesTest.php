<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Theme;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Theme\ManagedThemes;

use function file_put_contents;
use function json_encode;
use function mkdir;
use function str_repeat;
use function str_starts_with;
use function substr;
use function sys_get_temp_dir;
use function uniqid;

use const JSON_THROW_ON_ERROR;

final class ManagedThemesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-managed-themes-test-' . uniqid();
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->root);
    }

    public function testNoLockMeansNoManagedThemes(): void
    {
        self::assertSame([], ManagedThemes::fallbacks($this->root));
    }

    public function testNamesThemeDestinationsWithTheirFallback(): void
    {
        file_put_contents($this->root . '/yiipress.lock', self::lock([
            'yiipress/wonder' => 'themes/wonder',
            'acme/emoji' => 'processors/emoji',
            'acme/bare' => 'themes/bare',
        ], ['acme/bare' => 'none']));

        self::assertSame(
            ['wonder' => ['minimal', 'local'], 'bare' => ['local']],
            ManagedThemes::fallbacks($this->root),
        );
    }

    public function testInvalidLockIsAnError(): void
    {
        file_put_contents($this->root . '/yiipress.lock', '{');

        $this->expectException(InstallException::class);

        ManagedThemes::fallbacks($this->root);
    }

    /**
     * @param array<string, string> $destinations ID → destination.
     * @param array<string, string> $fallbacks ID → `fallback` other than `minimal`.
     */
    private static function lock(array $destinations, array $fallbacks = []): string
    {
        $extensions = [];
        foreach ($destinations as $id => $destination) {
            $extensions[$id] = [
                'archive' => [
                    'kind' => 'github-tarball',
                    'sha256' => str_repeat('a', 64),
                    'size' => 1,
                    'url' => 'https://codeload.github.com/acme/x/tar.gz/refs/tags/v1.0.0',
                ],
                'commit' => str_repeat('b', 40),
                'destination' => $destination,
                'files' => [],
                'manifest_sha256' => str_repeat('c', 64),
                'source' => 'https://github.com/acme/x',
                'tag' => 'v1.0.0',
                'type' => str_starts_with($destination, 'themes/') ? 'theme' : 'processor',
                'version' => '1.0.0',
            ] + (str_starts_with($destination, 'themes/')
                ? ['name' => substr($destination, 7), 'fallback' => $fallbacks[$id] ?? 'minimal']
                : []);
        }

        return json_encode(['extensions' => $extensions, 'lock' => 1], JSON_THROW_ON_ERROR);
    }
}

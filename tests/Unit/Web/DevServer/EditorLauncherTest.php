<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Web\DevServer;

use YiiPress\Web\DevServer\EditorLauncher;
use PHPUnit\Framework\TestCase;

use function mkdir;
use function sys_get_temp_dir;

final class EditorLauncherTest extends TestCase
{
    public function testConfiguredStringCommandAppendsFilePath(): void
    {
        $launcher = new EditorLauncher();

        self::assertSame(
            ['code', '--reuse-window', '/site/content/blog/post.md'],
            $launcher->command('/site/content/blog/post.md', 'code --reuse-window'),
        );
    }

    public function testConfiguredCommandReplacesFilePlaceholder(): void
    {
        $launcher = new EditorLauncher();

        self::assertSame(
            ['code', '--goto', '/site/content/blog/post.md'],
            $launcher->command('/site/content/blog/post.md', ['code', '--goto', '{file}']),
        );
    }

    public function testReadsConfiguredEditorFromConfigFile(): void
    {
        $root = sys_get_temp_dir() . '/yiipress-editor-config-' . uniqid();
        mkdir($root);
        file_put_contents($root . '/config.yaml', "title: Test\nlanguages: [en]\neditor:\n  - code\n  - --goto\n  - '{file}'\n");

        try {
            $launcher = new EditorLauncher();

            self::assertSame(['code', '--goto', '{file}'], $launcher->configuredEditorFromFile($root . '/config.yaml'));
        } finally {
            unlink($root . '/config.yaml');
            rmdir($root);
        }
    }
}

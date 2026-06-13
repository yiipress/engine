<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class ImportCommandTest extends TestCase
{
    private string $sourceDir;
    private string $contentDir;

    protected function setUp(): void
    {
        $this->sourceDir = sys_get_temp_dir() . '/yiipress-import-source-' . uniqid();
        $this->contentDir = sys_get_temp_dir() . '/yiipress-import-target-' . uniqid();
        mkdir($this->sourceDir, 0o755, true);
        mkdir($this->contentDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->sourceDir);
        $this->removeDir($this->contentDir);
    }

    public function testFailsForUnknownSourceType(): void
    {
        $result = $this->runImport('unknown', ['--directory' => $this->sourceDir]);

        assertSame(65, $result['exitCode']);
        assertStringContainsString('Unknown source type "unknown"', $result['output']);
    }

    public function testFailsForMissingRequiredOption(): void
    {
        $result = $this->runImport('telegram');

        assertSame(65, $result['exitCode']);
        assertStringContainsString('Missing required option --directory', $result['output']);
    }

    public function testImportsTelegramExport(): void
    {
        file_put_contents(
            $this->sourceDir . '/result.json',
            json_encode([
                'type' => 'public_channel',
                'messages' => [
                    [
                        'id' => 1,
                        'type' => 'message',
                        'date' => '2024-03-15T10:30:00',
                        'text' => 'Hello from Telegram',
                        'text_entities' => [
                            ['type' => 'plain', 'text' => 'Hello from Telegram'],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        $result = $this->runImport('telegram', ['--directory' => $this->sourceDir]);

        assertSame(0, $result['exitCode'], $result['output']);
        assertStringContainsString('Importing from telegram', $result['output']);
        assertStringContainsString('Imported: 1', $result['output']);
        assertStringContainsString('Import complete', $result['output']);
    }

    public function testImportsToCustomCollection(): void
    {
        file_put_contents(
            $this->sourceDir . '/result.json',
            json_encode([
                'type' => 'public_channel',
                'messages' => [
                    [
                        'id' => 1,
                        'type' => 'message',
                        'date' => '2024-03-15T10:30:00',
                        'text' => 'Channel post',
                        'text_entities' => [
                            ['type' => 'plain', 'text' => 'Channel post'],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        $result = $this->runImport('telegram', ['--directory' => $this->sourceDir, '--collection' => 'channel']);

        assertSame(0, $result['exitCode'], $result['output']);
        assertStringContainsString('Imported: 1', $result['output']);
    }

    public function testImportsWordPressExport(): void
    {
        $exportFile = $this->sourceDir . '/wordpress.xml';
        file_put_contents(
            $exportFile,
            '<?xml version="1.0" encoding="UTF-8" ?>'
            . '<rss version="2.0"'
            . ' xmlns:content="http://purl.org/rss/1.0/modules/content/"'
            . ' xmlns:wp="http://wordpress.org/export/1.2/">'
            . '<channel><item>'
            . '<title>Hello WordPress</title>'
            . '<content:encoded><![CDATA[Body.]]></content:encoded>'
            . '<wp:post_id>1</wp:post_id>'
            . '<wp:post_date>2024-03-15 10:30:00</wp:post_date>'
            . '<wp:post_name>hello-wordpress</wp:post_name>'
            . '<wp:status>publish</wp:status>'
            . '<wp:post_type>post</wp:post_type>'
            . '</item></channel></rss>',
        );

        $result = $this->runImport('wordpress', ['--file' => $exportFile]);

        assertSame(0, $result['exitCode'], $result['output']);
        assertStringContainsString('Importing from wordpress', $result['output']);
        assertStringContainsString('Imported: 1', $result['output']);
    }

    public function testShowsAvailableImportersOnError(): void
    {
        $result = $this->runImport('unknown', ['--directory' => $this->sourceDir]);

        assertSame(65, $result['exitCode']);
        assertStringContainsString('telegram', $result['output']);
        assertStringContainsString('wordpress', $result['output']);
    }

    /**
     * @param array<string, string> $options
     * @return array{exitCode: int, output: string}
     */
    private function runImport(string $source, array $options = []): array
    {
        $yii = dirname(__DIR__, 3) . '/yii';

        $cmd = $yii . ' import ' . escapeshellarg($source)
            . ' --content-dir=' . escapeshellarg($this->contentDir);

        foreach ($options as $name => $value) {
            $cmd .= ' ' . $name . '=' . escapeshellarg($value);
        }

        $cmd .= ' 2>&1';

        exec($cmd, $output, $exitCode);

        return [
            'exitCode' => $exitCode,
            'output' => implode("\n", $output),
        ];
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}

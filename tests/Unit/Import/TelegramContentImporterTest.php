<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\TelegramContentImporter;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class TelegramContentImporterTest extends TestCase
{
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->sourceDir = sys_get_temp_dir() . '/yiipress-telegram-source-' . uniqid();
        $this->targetDir = sys_get_temp_dir() . '/yiipress-telegram-target-' . uniqid();
        mkdir($this->sourceDir, 0o755, true);
        mkdir($this->targetDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->sourceDir);
        $this->removeDir($this->targetDir);
    }

    public function testReturnsWarningWhenResultJsonMissing(): void
    {
        $importer = new TelegramContentImporter();

        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(0, $result->importedCount());
        assertCount(1, $result->warnings());
        assertStringContainsString('result.json not found', $result->warnings()[0]);
    }

    public function testImportsSimpleTextMessage(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => 'Hello from Telegram channel',
                    'text_entities' => [
                        ['type' => 'plain', 'text' => 'Hello from Telegram channel'],
                    ],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(1, $result->importedCount());
        assertCount(1, $result->importedFiles());

        $content = file_get_contents($result->importedFiles()[0]);
        assertStringContainsString('title: Hello from Telegram channel', $content);
        assertStringContainsString('date: 2024-03-15 10:30:00', $content);
        assertStringContainsString('Hello from Telegram channel', $content);
    }

    public function testExtractsHashtagsAsTags(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => 'Great post about PHP #php #webdev',
                    'text_entities' => [
                        ['type' => 'plain', 'text' => 'Great post about PHP '],
                        ['type' => 'hashtag', 'text' => '#php'],
                        ['type' => 'plain', 'text' => ' '],
                        ['type' => 'hashtag', 'text' => '#webdev'],
                    ],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(1, $result->importedCount());

        $content = file_get_contents($result->importedFiles()[0]);
        assertStringContainsString("tags:\n  - php\n  - webdev", $content);
    }

    public function testConvertsBoldAndItalicMarkup(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => [
                        'Some ',
                        ['type' => 'bold', 'text' => 'bold'],
                        ' and ',
                        ['type' => 'italic', 'text' => 'italic'],
                        ' text',
                    ],
                    'text_entities' => [],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        $content = file_get_contents($result->importedFiles()[0]);
        assertStringContainsString('**bold**', $content);
        assertStringContainsString('*italic*', $content);
    }

    public function testConvertsTextLinks(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => [
                        'Visit ',
                        ['type' => 'text_link', 'text' => 'our site', 'href' => 'https://example.com'],
                        ' for more',
                    ],
                    'text_entities' => [],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        $content = file_get_contents($result->importedFiles()[0]);
        assertStringContainsString('[our site](https://example.com)', $content);
    }

    public function testConvertsCodeBlocks(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => [
                        'Use ',
                        ['type' => 'code', 'text' => 'echo "hi"'],
                        ' in PHP',
                    ],
                    'text_entities' => [],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        $content = file_get_contents($result->importedFiles()[0]);
        assertStringContainsString('`echo "hi"`', $content);
    }

    public function testSkipsEmptyMessages(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => '',
                    'text_entities' => [],
                ],
                [
                    'id' => 2,
                    'type' => 'message',
                    'date' => '2024-03-15T11:00:00',
                    'text' => 'Real content',
                    'text_entities' => [
                        ['type' => 'plain', 'text' => 'Real content'],
                    ],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(1, $result->importedCount());
        assertCount(1, $result->skippedFiles());
    }

    public function testSkipsServiceMessages(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'service',
                    'date' => '2024-03-15T10:30:00',
                    'text' => '',
                    'text_entities' => [],
                ],
                [
                    'id' => 2,
                    'type' => 'message',
                    'date' => '2024-03-15T11:00:00',
                    'text' => 'Actual post',
                    'text_entities' => [
                        ['type' => 'plain', 'text' => 'Actual post'],
                    ],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(1, $result->importedCount());
    }

    public function testCreatesCollectionConfigIfMissing(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => 'Test post',
                    'text_entities' => [
                        ['type' => 'plain', 'text' => 'Test post'],
                    ],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertFileExists($this->targetDir . '/blog/_collection.yaml');

        $config = file_get_contents($this->targetDir . '/blog/_collection.yaml');
        assertStringContainsString('sort_by: date', $config);
    }

    public function testDoesNotOverwriteExistingCollectionConfig(): void
    {
        mkdir($this->targetDir . '/blog', 0o755, true);
        file_put_contents($this->targetDir . '/blog/_collection.yaml', "title: My Blog\nsort_by: weight\n");

        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => 'Test post',
                    'text_entities' => [
                        ['type' => 'plain', 'text' => 'Test post'],
                    ],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        $config = file_get_contents($this->targetDir . '/blog/_collection.yaml');
        assertStringContainsString('sort_by: weight', $config);
    }

    public function testImportsMultipleMessages(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => 'First post',
                    'text_entities' => [['type' => 'plain', 'text' => 'First post']],
                ],
                [
                    'id' => 2,
                    'type' => 'message',
                    'date' => '2024-03-16T12:00:00',
                    'text' => 'Second post',
                    'text_entities' => [['type' => 'plain', 'text' => 'Second post']],
                ],
                [
                    'id' => 3,
                    'type' => 'message',
                    'date' => '2024-03-17T14:00:00',
                    'text' => 'Third post',
                    'text_entities' => [['type' => 'plain', 'text' => 'Third post']],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(3, $result->totalMessages());
        assertSame(3, $result->importedCount());
        assertCount(3, $result->importedFiles());
    }

    public function testHashtagsRemovedFromBody(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => [
                        'Learn PHP today ',
                        ['type' => 'hashtag', 'text' => '#php'],
                        ' ',
                        ['type' => 'hashtag', 'text' => '#learning'],
                    ],
                    'text_entities' => [],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        $content = file_get_contents($result->importedFiles()[0]);
        assertStringContainsString("tags:\n  - php\n  - learning", $content);
        assertStringNotContainsString('#php', $content);
        assertStringNotContainsString('#learning', $content);
    }

    public function testCopiesPhotoAsset(): void
    {
        mkdir($this->sourceDir . '/photos', 0o755, true);
        file_put_contents($this->sourceDir . '/photos/photo_1.jpg', 'fake-image-data');

        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => 'Check this photo',
                    'text_entities' => [['type' => 'plain', 'text' => 'Check this photo']],
                    'photo' => 'photos/photo_1.jpg',
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertFileExists($this->targetDir . '/blog/assets/photo_1.jpg');

        $content = file_get_contents($result->importedFiles()[0]);
        assertStringContainsString('![](/blog/assets/photo_1.jpg)', $content);
    }

    public function testImporterNameIsTelegram(): void
    {
        $importer = new TelegramContentImporter();

        assertSame('telegram', $importer->name());
    }

    public function testDeclaresDirectoryOption(): void
    {
        $importer = new TelegramContentImporter();
        $options = $importer->options();

        assertCount(1, $options);
        assertSame('directory', $options[0]->name);
        assertSame(true, $options[0]->required);
    }

    public function testReturnsWarningWhenDirectoryOptionMissing(): void
    {
        $importer = new TelegramContentImporter();

        $result = $importer->import([], $this->targetDir, 'blog');

        assertSame(0, $result->importedCount());
        assertCount(1, $result->warnings());
        assertStringContainsString('directory option is required', $result->warnings()[0]);
    }

    public function testHandlesFullExportWithChatsStructure(): void
    {
        $this->writeResultJson([
            'chats' => [
                'list' => [
                    [
                        'name' => 'My Channel',
                        'type' => 'public_channel',
                        'messages' => [
                            [
                                'id' => 1,
                                'type' => 'message',
                                'date' => '2024-03-15T10:30:00',
                                'text' => 'Channel post',
                                'text_entities' => [['type' => 'plain', 'text' => 'Channel post']],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(1, $result->importedCount());
    }

    public function testConvertsStrikethroughMarkup(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => [
                        'This is ',
                        ['type' => 'strikethrough', 'text' => 'deleted'],
                        ' text',
                    ],
                    'text_entities' => [],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        $content = file_get_contents($result->importedFiles()[0]);
        assertStringContainsString('~~deleted~~', $content);
    }

    public function testConvertsPreformattedCodeBlock(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => [
                        'Example:',
                        ['type' => 'pre', 'text' => 'echo "hello";'],
                    ],
                    'text_entities' => [],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        $content = file_get_contents($result->importedFiles()[0]);
        assertStringContainsString("```\necho \"hello\";\n```", $content);
    }

    public function testEscapesTitleWithSpecialYamlCharacters(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => 'What is "PHP 8.5"?',
                    'text_entities' => [['type' => 'plain', 'text' => 'What is "PHP 8.5"?']],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        $content = file_get_contents($result->importedFiles()[0]);
        assertStringContainsString('"What is \\"PHP 8.5\\"?"', $content);
    }

    public function testDeduplicatesHashtags(): void
    {
        $this->writeResultJson([
            'messages' => [
                [
                    'id' => 1,
                    'type' => 'message',
                    'date' => '2024-03-15T10:30:00',
                    'text' => [
                        'Post ',
                        ['type' => 'hashtag', 'text' => '#php'],
                        ' ',
                        ['type' => 'hashtag', 'text' => '#PHP'],
                    ],
                    'text_entities' => [],
                ],
            ],
        ]);

        $importer = new TelegramContentImporter();
        $result = $importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        $content = file_get_contents($result->importedFiles()[0]);
        assertSame(1, substr_count($content, '  - php'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeResultJson(array $data): void
    {
        file_put_contents(
            $this->sourceDir . '/result.json',
            data: json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
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

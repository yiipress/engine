<?php

declare(strict_types=1);

namespace App\Benchmarks;

use App\Import\Telegram\TelegramContentImporter;
use DateTimeImmutable;
use FilesystemIterator;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class TelegramImporterBench
{
    private TelegramContentImporter $importer;
    private string $sourceDir;
    private string $targetDir;

    public function setUp(): void
    {
        $this->importer = new TelegramContentImporter();
        $this->sourceDir = sys_get_temp_dir() . '/yiipress-bench-telegram-source';
        $this->targetDir = sys_get_temp_dir() . '/yiipress-bench-telegram-target';

        if (!is_dir($this->sourceDir)) {
            mkdir($this->sourceDir, 0o755, true);
        }

        $this->generateExport(100);
    }

    public function tearDown(): void
    {
        $this->removeDir($this->targetDir);
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchImport100Messages(): void
    {
        $this->removeDir($this->targetDir);
        $this->importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchImport1000Messages(): void
    {
        $this->generateExport(1000);
        $this->removeDir($this->targetDir);
        $this->importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');
    }

    private function generateExport(int $count): void
    {
        $messages = [];
        $tags = ['php', 'webdev', 'programming', 'tutorial', 'news', 'release', 'framework', 'performance'];
        $baseDate = new DateTimeImmutable('2024-01-01T10:00:00');

        for ($i = 1; $i <= $count; $i++) {
            $date = $baseDate->modify('+' . $i . ' hours');
            $messageTags = array_slice($tags, $i % count($tags), 2);

            $textParts = [
                'Post number ' . $i . ' about various topics. ',
                ['type' => 'bold', 'text' => 'Important information'],
                ' with ',
                ['type' => 'italic', 'text' => 'some details'],
                ' and ',
                ['type' => 'text_link', 'text' => 'a link', 'href' => 'https://example.com/' . $i],
                ".\n\nThis is the second paragraph with more content to make the message realistic. ",
                ['type' => 'code', 'text' => '$variable = ' . $i],
                ' is used here.',
            ];

            foreach ($messageTags as $tag) {
                $textParts[] = ' ';
                $textParts[] = ['type' => 'hashtag', 'text' => '#' . $tag];
            }

            $messages[] = [
                'id' => $i,
                'type' => 'message',
                'date' => $date->format('Y-m-d\TH:i:s'),
                'text' => $textParts,
                'text_entities' => [],
            ];
        }

        file_put_contents(
            $this->sourceDir . '/result.json',
            json_encode(['messages' => $messages], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
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

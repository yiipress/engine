<?php

declare(strict_types=1);

namespace App\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class NewCommandTest extends TestCase
{
    private string $contentDir;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/yiipress-new-test-' . uniqid();
        mkdir($this->contentDir . '/blog', 0o755, true);

        file_put_contents($this->contentDir . '/config.yaml', <<<'YAML'
title: Test Site
default_author: john-doe
YAML);

        file_put_contents($this->contentDir . '/blog/_collection.yaml', <<<'YAML'
title: Blog
sort_by: date
YAML);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->contentDir);
    }

    public function testCreatesCollectionEntryWithDatePrefix(): void
    {
        $result = $this->runNew('My First Post', collection: 'blog');

        assertSame(0, $result['exitCode'], $result['output']);
        assertStringContainsString('Created:', $result['output']);

        $expected = $this->contentDir . '/blog/' . date('Y-m-d') . '-my-first-post.md';
        assertFileExists($expected);

        $content = file_get_contents($expected);
        assertStringContainsString('title: My First Post', $content);
        assertStringContainsString('john-doe', $content);
    }

    public function testCreatesCollectionEntryWithDraftFlag(): void
    {
        $result = $this->runNew('Draft Post', collection: 'blog', draft: true);

        assertSame(0, $result['exitCode'], $result['output']);

        $expected = $this->contentDir . '/blog/' . date('Y-m-d') . '-draft-post.md';
        assertFileExists($expected);

        $content = file_get_contents($expected);
        assertStringContainsString('draft: true', $content);
    }

    public function testCreatesStandalonePage(): void
    {
        $result = $this->runNew('About Us');

        assertSame(0, $result['exitCode'], $result['output']);

        $expected = $this->contentDir . '/about-us.md';
        assertFileExists($expected);

        $content = file_get_contents($expected);
        assertStringContainsString('title: About Us', $content);
        assertStringContainsString('permalink: /about-us/', $content);
    }

    public function testFailsForNonExistentCollection(): void
    {
        $result = $this->runNew('Post', collection: 'nonexistent');

        assertSame(65, $result['exitCode']);
        assertStringContainsString('Collection "nonexistent" not found', $result['output']);
    }

    public function testFailsIfFileAlreadyExists(): void
    {
        $this->runNew('My Post', collection: 'blog');
        $result = $this->runNew('My Post', collection: 'blog');

        assertSame(65, $result['exitCode']);
        assertStringContainsString('File already exists', $result['output']);
    }

    public function testCreatesEntryWithoutDatePrefixForWeightSortedCollection(): void
    {
        mkdir($this->contentDir . '/page', 0o755, true);
        file_put_contents($this->contentDir . '/page/_collection.yaml', <<<'YAML'
title: Pages
sort_by: weight
YAML);

        $result = $this->runNew('Services', collection: 'page');

        assertSame(0, $result['exitCode'], $result['output']);

        $expected = $this->contentDir . '/page/services.md';
        assertFileExists($expected);
    }

    public function testEscapesTitleWithSpecialCharacters(): void
    {
        $result = $this->runNew('What is "YiiPress"?', collection: 'blog');

        assertSame(0, $result['exitCode'], $result['output']);

        $expected = $this->contentDir . '/blog/' . date('Y-m-d') . '-what-is-yiipress.md';
        assertFileExists($expected);

        $content = file_get_contents($expected);
        assertStringContainsString('"What is \\"YiiPress\\"?"', $content);
    }

    public function testStandalonePageWithoutDefaultAuthor(): void
    {
        file_put_contents($this->contentDir . '/config.yaml', "title: Test Site\n");

        $result = $this->runNew('Contact');

        assertSame(0, $result['exitCode'], $result['output']);

        $content = file_get_contents($this->contentDir . '/contact.md');
        assertStringNotContainsString('authors', $content);
    }

    /**
     * @return array{exitCode: int, output: string}
     */
    private function runNew(string $title, ?string $collection = null, bool $draft = false): array
    {
        $yii = dirname(__DIR__, 3) . '/yii';

        $cmd = $yii . ' new ' . escapeshellarg($title)
            . ' --content-dir=' . escapeshellarg($this->contentDir);

        if ($collection !== null) {
            $cmd .= ' --collection=' . escapeshellarg($collection);
        }

        if ($draft) {
            $cmd .= ' --draft';
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

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}

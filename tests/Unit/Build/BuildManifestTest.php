<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\BuildManifest;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

final class BuildManifestTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress_manifest_test_' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testNewFileIsDetectedAsChanged(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        file_put_contents($sourceFile, '# Hello');

        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->load();

        assertTrue($manifest->isChanged($sourceFile));
    }

    public function testRecordedFileIsNotChanged(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        file_put_contents($sourceFile, '# Hello');

        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->load();
        $manifest->record($sourceFile, [$this->tempDir . '/output/entry/index.html']);
        $manifest->save();

        $manifest2 = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest2->load();

        assertFalse($manifest2->isChanged($sourceFile));
    }

    public function testModifiedFileIsDetectedAsChanged(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        file_put_contents($sourceFile, '# Hello');

        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->load();
        $manifest->record($sourceFile, [$this->tempDir . '/output/entry/index.html']);
        $manifest->save();

        file_put_contents($sourceFile, '# Updated');

        $manifest2 = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest2->load();

        assertTrue($manifest2->isChanged($sourceFile));
    }

    public function testRemovedOutputsReturnsStaleFiles(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        file_put_contents($sourceFile, '# Hello');
        $outputFile = $this->tempDir . '/output/entry/index.html';

        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->load();
        $manifest->record($sourceFile, [$outputFile]);
        $manifest->save();

        unlink($sourceFile);

        $manifest2 = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest2->load();

        $removed = $manifest2->removedOutputs([]);

        assertSame([$outputFile], $removed);
    }

    public function testChangedFilesReturnsOnlyModified(): void
    {
        $file1 = $this->tempDir . '/a.md';
        $file2 = $this->tempDir . '/b.md';
        file_put_contents($file1, 'aaa');
        file_put_contents($file2, 'bbb');

        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->load();
        $manifest->record($file1, ['/out/a/index.html']);
        $manifest->record($file2, ['/out/b/index.html']);
        $manifest->save();

        file_put_contents($file2, 'bbb-changed');

        $manifest2 = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest2->load();

        $changed = $manifest2->changedFiles([$file1, $file2]);

        assertSame([$file2], $changed);
    }

    public function testEmptyManifestLoadsCleanly(): void
    {
        $manifest = new BuildManifest($this->tempDir . '/nonexistent.json');
        $manifest->load();

        $sourceFile = $this->tempDir . '/entry.md';
        file_put_contents($sourceFile, '# Hello');

        assertTrue($manifest->isChanged($sourceFile));
        assertSame([], $manifest->removedOutputs([$sourceFile]));
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

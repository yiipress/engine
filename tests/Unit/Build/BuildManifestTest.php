<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\BuildManifest;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

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

    public function testRemappingOutputDirectoryPreservesVerifiedSourceMetadata(): void
    {
        $source = $this->tempDir . '/entry.md';
        file_put_contents($source, '# Hello');
        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->record($source, ['/old/page/index.html', '/old-other/index.html']);
        $recorded = $manifest->entries()[$source];
        // Remapping must not read or hash the source again.
        unlink($source);
        $manifest->remapOutputDirectory('/old', '/new');
        $recorded['outputs'] = ['/new/page/index.html', '/old-other/index.html'];
        assertSame($recorded, $manifest->entries()[$source]);
        $manifest->save();
        $manifest->load();
        foreach ($recorded as $key => $value) {
            assertSame($value, $manifest->entries()[$source][$key]);
        }
    }

    public function testChangedSourceIsRevalidatedBeforeRecording(): void
    {
        $source = $this->tempDir . '/entry.md';
        file_put_contents($source, 'old');
        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->record($source, []);
        file_put_contents($source, 'changed once');
        assertTrue($manifest->isChanged($source));
        $mtime = filemtime($source);
        file_put_contents($source, 'changed more');
        touch($source, $mtime);
        $manifest->record($source, []);
        assertSame(hash_file('xxh128', $source), $manifest->entries()[$source]['hash']);
    }

    public function testSameSizeSameTimestampEditsAreRehashedWhenRecorded(): void
    {
        $source = $this->tempDir . '/entry.md';
        file_put_contents($source, 'first');
        touch($source, 100);
        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->record($source, []);
        file_put_contents($source, 'other');
        touch($source, 100);
        assertTrue($manifest->isChanged($source));
        file_put_contents($source, 'third');
        touch($source, 100);
        $manifest->record($source, []);
        assertSame(hash_file('xxh128', $source), $manifest->entries()[$source]['hash']);
        file_put_contents($source, 'other');
        touch($source, 100);
        assertTrue($manifest->isChanged($source), 'Restoring the first edit must invalidate the second edit output.');
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

    public function testChangeDetectionAndRecordingRefreshCachedFileMetadata(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        file_put_contents($sourceFile, 'old');
        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->record($sourceFile, []);
        assertFalse($manifest->isChanged($sourceFile));

        $handle = fopen($sourceFile, 'ab');
        self::assertNotFalse($handle);
        fwrite($handle, ' appended');
        fclose($handle);
        assertTrue($manifest->isChanged($sourceFile));
        $manifest->record($sourceFile, []);
        assertFalse($manifest->isChanged($sourceFile));
        assertSame(12, $manifest->entries()[$sourceFile]['size']);
    }

    public function testSameSizeEditWithPreservedTimestampIsDetected(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        file_put_contents($sourceFile, 'first');
        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->record($sourceFile, []);
        $mtime = filemtime($sourceFile);
        file_put_contents($sourceFile, 'other');
        touch($sourceFile, $mtime);
        assertTrue($manifest->isChanged($sourceFile));
    }

    public function testDirectoryInventoryDetectsAdditionWithPreservedTimestamp(): void
    {
        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $mtime = filemtime($this->tempDir);
        $manifest->setTrackedDirectories([$this->tempDir => $mtime]);
        file_put_contents($this->tempDir . '/new.md', 'new');
        touch($this->tempDir, $mtime);
        assertTrue($manifest->trackedDirectoriesChanged());
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

    public function testCorruptedManifestLoadsAsEmptyManifest(): void
    {
        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, '{"entries":');

        $manifest = new BuildManifest($manifestPath);
        $manifest->load();

        assertSame([], $manifest->entries());
        assertSame([], $manifest->configFiles());
        assertSame([], $manifest->sourceFiles());
    }

    public function testInvalidCurrentManifestSchemaLoadsAsEmptyManifest(): void
    {
        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'entries' => ['/entry.md' => ['hash' => 'hash', 'outputs' => [42]]],
            'configFiles' => ['config.yaml'],
            'trackedDirectories' => [],
        ], JSON_THROW_ON_ERROR));

        $manifest = new BuildManifest($manifestPath);
        $manifest->load();

        assertSame([], $manifest->entries());
    }

    public function testInvalidLegacyManifestSchemaLoadsAsEmptyManifest(): void
    {
        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode(['/entry.md' => ['hash' => [], 'outputs' => []]], JSON_THROW_ON_ERROR));

        $manifest = new BuildManifest($manifestPath);
        $manifest->load();

        assertSame([], $manifest->entries());
    }

    public function testNullEntryMetadataLoadsAsEmptyManifest(): void
    {
        foreach (['mtime', 'size'] as $metadata) {
            $manifestPath = $this->tempDir . "/manifest-$metadata.json";
            file_put_contents($manifestPath, json_encode([
                'entries' => ['/entry.md' => ['hash' => 'hash', 'outputs' => [], $metadata => null]],
                'configFiles' => [],
                'trackedDirectories' => [],
            ], JSON_THROW_ON_ERROR));

            $manifest = new BuildManifest($manifestPath);
            $manifest->load();

            assertSame([], $manifest->entries());
        }
    }

    public function testRecordRejectsMissingSource(): void
    {
        $manifest = new BuildManifest($this->tempDir . '/manifest.json');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to hash source file:');

        $manifest->record($this->tempDir . '/missing.md', []);
    }

    public function testInvalidManifestPayloadClearsPreviouslyLoadedMetadata(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        $contentDir = $this->tempDir . '/content';
        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($sourceFile, '# Hello');
        mkdir($contentDir, 0o755, true);

        $manifest = new BuildManifest($manifestPath);
        $manifest->record($sourceFile, ['/out/entry/index.html']);
        $manifest->setConfigFiles([$contentDir . '/config.yaml']);
        $manifest->setTrackedDirectories([$contentDir => (int) filemtime($contentDir)]);
        $manifest->save();
        $manifest->load();

        file_put_contents($manifestPath, '"invalid"');
        $manifest->load();

        assertSame([], $manifest->entries());
        assertSame([], $manifest->configFiles());
        assertSame([], $manifest->sourceFiles());
        assertFalse($manifest->hasTrackedDirectories());
    }

    public function testMissingManifestClearsPreviouslyLoadedMetadata(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        $contentDir = $this->tempDir . '/content';
        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($sourceFile, '# Hello');
        mkdir($contentDir, 0o755, true);

        $manifest = new BuildManifest($manifestPath);
        $manifest->record($sourceFile, ['/out/entry/index.html']);
        $manifest->setConfigFiles([$contentDir . '/config.yaml']);
        $manifest->setTrackedDirectories([$contentDir => (int) filemtime($contentDir)]);
        $manifest->save();
        $manifest->load();

        unlink($manifestPath);
        $manifest->load();

        assertSame([], $manifest->entries());
        assertSame([], $manifest->configFiles());
        assertSame([], $manifest->sourceFiles());
        assertFalse($manifest->hasTrackedDirectories());
    }

    public function testSaveDoesNotLeaveTemporaryManifestFile(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        file_put_contents($sourceFile, '# Hello');
        $manifestPath = $this->tempDir . '/manifest.json';

        $manifest = new BuildManifest($manifestPath);
        $manifest->record($sourceFile, ['/out/entry/index.html']);
        $manifest->save();

        assertTrue(is_file($manifestPath));
        assertSame([], $this->manifestTemporaryFiles());
    }

    public function testSaveCleansTemporaryManifestFileWhenWriteFails(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        file_put_contents($sourceFile, '# Hello');
        $manifestPath = $this->tempDir . '/' . str_repeat('a', 240) . '.json';

        $manifest = new BuildManifest($manifestPath);
        $manifest->record($sourceFile, ['/out/entry/index.html']);

        try {
            $manifest->save();
            self::fail('Expected manifest save to fail.');
        } catch (RuntimeException $exception) {
            assertTrue(str_starts_with($exception->getMessage(), 'Unable to write file "'));
        }

        assertSame([], $this->manifestTemporaryFiles());
    }

    public function testSaveCleansTemporaryManifestFileWhenRenameFails(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        file_put_contents($sourceFile, '# Hello');
        $manifestPath = $this->tempDir . '/manifest.json';
        mkdir($manifestPath, 0o755);

        $manifest = new BuildManifest($manifestPath);
        $manifest->record($sourceFile, ['/out/entry/index.html']);

        try {
            $manifest->save();
            self::fail('Expected manifest save to fail.');
        } catch (RuntimeException $exception) {
            assertSame(sprintf('Unable to replace file "%s".', $manifestPath), $exception->getMessage());
        }

        assertTrue(is_dir($manifestPath));
        assertSame([], $this->manifestTemporaryFiles());
    }

    public function testReplaceReturnsOutputsThatAreNoLongerReferenced(): void
    {
        $sourceFile = $this->tempDir . '/asset.css';
        file_put_contents($sourceFile, 'body{}');

        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->load();
        $manifest->record($sourceFile, ['/out/style.old.css']);

        $staleOutputs = $manifest->replace($sourceFile, ['/out/style.new.css']);

        assertSame(['/out/style.old.css'], $staleOutputs);
    }

    public function testRecordRevalidatesStoredHashWhenMtimeAndSizeMatch(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        file_put_contents($sourceFile, '# Hello');
        $manifestPath = $this->tempDir . '/manifest.json';

        file_put_contents($manifestPath, json_encode([
            'entries' => [
                $sourceFile => [
                    'hash' => 'stored-hash',
                    'mtime' => (int) filemtime($sourceFile),
                    'size' => (int) filesize($sourceFile),
                    'outputs' => ['/out/old.html'],
                ],
            ],
            'configFiles' => [],
            'trackedDirectories' => [],
        ], JSON_THROW_ON_ERROR));

        $manifest = new BuildManifest($manifestPath);
        $manifest->load();
        $manifest->record($sourceFile, ['/out/new.html']);

        $entry = $manifest->entries()[$sourceFile];
        assertSame(hash_file('xxh128', $sourceFile), $entry['hash']);
        assertSame(['/out/new.html'], $entry['outputs']);
    }

    public function testMissingOutputFilesReturnsSourceWithMissingOutputs(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        file_put_contents($sourceFile, '# Hello');

        $existingOutput = $this->tempDir . '/output/entry/index.html';
        mkdir(dirname($existingOutput), 0o755, true);
        file_put_contents($existingOutput, '<html></html>');

        $missingOutput = $this->tempDir . '/output/entry/feed.xml';

        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->load();
        $manifest->record($sourceFile, [$existingOutput, $missingOutput]);

        assertSame([$sourceFile], $manifest->missingOutputFiles([$sourceFile]));
    }

    public function testSaveAndLoadPersistsConfigFilesAndTrackedDirectories(): void
    {
        $sourceFile = $this->tempDir . '/entry.md';
        file_put_contents($sourceFile, '# Hello');
        mkdir($this->tempDir . '/content', 0o755, true);

        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->record($sourceFile, ['/out/entry/index.html']);
        $manifest->setConfigFiles([$this->tempDir . '/content/config.yaml']);
        $manifest->setTrackedDirectories([$this->tempDir . '/content' => (int) filemtime($this->tempDir . '/content')]);
        $manifest->save();

        $loaded = new BuildManifest($this->tempDir . '/manifest.json');
        $loaded->load();

        assertSame([$this->tempDir . '/content/config.yaml'], $loaded->configFiles());
        assertSame([$sourceFile], $loaded->sourceFiles());
        assertFalse($loaded->trackedDirectoriesChanged());
    }

    public function testTrackedDirectoriesChangedReturnsTrueWhenDirectoryMtimeChanges(): void
    {
        $directory = $this->tempDir . '/content';
        mkdir($directory, 0o755, true);

        $manifest = new BuildManifest($this->tempDir . '/manifest.json');
        $manifest->setTrackedDirectories([$directory => (int) filemtime($directory)]);

        sleep(1);
        mkdir($directory . '/blog', 0o755, true);

        assertTrue($manifest->trackedDirectoriesChanged());
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

    /**
     * @return list<string>
     */
    private function manifestTemporaryFiles(): array
    {
        $files = glob($this->tempDir . '/.*.tmp');

        return $files === false ? [] : array_values($files);
    }
}

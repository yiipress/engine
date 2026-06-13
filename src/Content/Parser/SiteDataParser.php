<?php

declare(strict_types=1);

namespace YiiPress\Content\Parser;

use FilesystemIterator;
use SplFileInfo;

use function basename;
use function file_get_contents;
use function is_dir;
use function ksort;
use function strtolower;
use function yaml_parse;

final class SiteDataParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $dataDir): array
    {
        if (!is_dir($dataDir)) {
            return [];
        }

        $data = [];
        $iterator = new FilesystemIterator($dataDir, FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                continue;
            }

            $extension = strtolower($item->getExtension());
            if ($extension !== 'yaml' && $extension !== 'yml') {
                continue;
            }

            $path = $item->getPathname();
            $content = file_get_contents($path);
            if ($content === false) {
                throw new InvalidContentConfigException(
                    "Cannot read site data file: $path",
                    $path,
                    'Check that the file exists and is readable by the build process.',
                );
            }

            $parsed = @yaml_parse($content);
            if ($parsed === false) {
                throw new InvalidContentConfigException(
                    "Invalid YAML in site data file: $path",
                    $path,
                    'Fix the YAML syntax in content/data/*.yaml, then run the build again.',
                );
            }

            $key = basename($item->getFilename(), '.' . $extension);
            $data[$key] = $parsed;
        }

        ksort($data, SORT_STRING);

        return $data;
    }
}

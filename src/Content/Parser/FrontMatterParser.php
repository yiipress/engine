<?php

declare(strict_types=1);

namespace YiiPress\Content\Parser;

use RuntimeException;

use function array_is_list;
use function fclose;
use function fgets;
use function fopen;
use function ftell;
use function str_starts_with;
use function yaml_parse;

final class FrontMatterParser
{
    /**
     * Extracts YAML front matter from a file without reading the Markdown body into memory.
     *
     * Returns the parsed front matter as an associative array, plus the byte offset
     * and length of the body for deferred reading.
     *
     * @return array{frontMatter: array<string, mixed>, bodyOffset: int, bodyLength: int}
     */
    public function parse(string $filePath): array
    {
        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            throw new RuntimeException("Cannot read file: $filePath");
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open file: $filePath");
        }

        try {
            return $this->extract($handle, $fileSize, $filePath);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     * @return array{frontMatter: array<string, mixed>, bodyOffset: int, bodyLength: int}
     */
    private function extract($handle, int $fileSize, string $filePath): array
    {
        $firstLine = fgets($handle);
        if ($firstLine === false || !str_starts_with(trim($firstLine), '---')) {
            $result = [
                'frontMatter' => [],
                'bodyOffset' => 0,
                'bodyLength' => $fileSize,
            ];

            return $this->inferTitle($handle, $result, 0);
        }

        $yamlLines = [];
        while (($line = fgets($handle)) !== false) {
            if (str_starts_with(trim($line), '---')) {
                $bodyOffset = ftell($handle);
                if ($bodyOffset === false) {
                    $bodyOffset = $fileSize;
                }

                $yaml = implode('', $yamlLines);
                $parsed = trim($yaml) === '' ? null : @yaml_parse($yaml);
                if ($parsed === false) {
                    throw new InvalidContentConfigException(
                        "Invalid YAML in front matter: $filePath",
                        $filePath,
                        "Fix the YAML front matter between the opening and closing --- markers in $filePath.",
                    );
                }

                if ($parsed === null) {
                    $parsed = [];
                }

                if (!is_array($parsed) || ($parsed !== [] && array_is_list($parsed))) {
                    throw new InvalidContentConfigException(
                        "Front matter must contain YAML key-value pairs: $filePath",
                        $filePath,
                        implode("\n", [
                            'Use mappings such as:',
                            'title: My Post',
                            'tags: [php, yii]',
                        ]),
                    );
                }

                $result = [
                    'frontMatter' => $parsed,
                    'bodyOffset' => $bodyOffset,
                    'bodyLength' => $fileSize - $bodyOffset,
                ];

                if (!isset($parsed['title']) || (string) $parsed['title'] === '') {
                    return $this->inferTitle($handle, $result, $bodyOffset);
                }

                return $result;
            }
            $yamlLines[] = $line;
        }

        return [
            'frontMatter' => [],
            'bodyOffset' => 0,
            'bodyLength' => $fileSize,
        ];
    }

    /**
     * @param resource $handle
     * @param array{frontMatter: array<string, mixed>, bodyOffset: int, bodyLength: int} $result
     * @return array{frontMatter: array<string, mixed>, bodyOffset: int, bodyLength: int}
     */
    private function inferTitle($handle, array $result, int $seekTo): array
    {
        fseek($handle, $seekTo);

        for ($i = 0; $i < 2; $i++) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }

            $trimmed = trim($line);
            if (str_starts_with($trimmed, '# ')) {
                $result['frontMatter']['title'] = substr($trimmed, 2);
                $newOffset = ftell($handle);
                if ($newOffset !== false) {
                    $result['bodyLength'] -= ($newOffset - $result['bodyOffset']);
                    $result['bodyOffset'] = $newOffset;
                }
                return $result;
            }

            if ($trimmed !== '') {
                break;
            }
        }

        return $result;
    }
}

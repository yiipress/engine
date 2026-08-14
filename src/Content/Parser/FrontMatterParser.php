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
     * @return array{frontMatter: array<string, mixed>, bodyOffset: int<0, max>, bodyLength: int<0, max>}
     */
    public function parse(string $filePath): array
    {
        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            throw new RuntimeException("Cannot read file: $filePath");
        }
        /** @var int<0, max> $fileSize */

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
     * @param int<0, max> $fileSize
     * @return array{frontMatter: array<string, mixed>, bodyOffset: int<0, max>, bodyLength: int<0, max>}
     */
    private function extract($handle, int $fileSize, string $filePath): array
    {
        $firstLine = fgets($handle);
        if ($firstLine === false || !str_starts_with(trim($firstLine), '---')) {
            /** @var array<string, mixed> $frontMatter */
            $frontMatter = [];
            /** @var int<0, max> $bodyLength */
            $bodyLength = $fileSize;
            $result = [
                'frontMatter' => $frontMatter,
                'bodyOffset' => 0,
                'bodyLength' => $bodyLength,
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
                /** @var int<0, max> $bodyOffset */

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

                /** @var array<string, mixed> $parsed */

                /** @var int<0, max> $bodyLength */
                $bodyLength = $fileSize - $bodyOffset;
                $result = [
                    'frontMatter' => $parsed,
                    'bodyOffset' => $bodyOffset,
                    'bodyLength' => $bodyLength,
                ];

                /** @var scalar|null $title */
                $title = $parsed['title'] ?? null;
                if ((string) $title === '') {
                    return $this->inferTitle($handle, $result, $bodyOffset);
                }

                return $result;
            }
            $yamlLines[] = $line;
        }

        /** @var array<string, mixed> $frontMatter */
        $frontMatter = [];
        /** @var int<0, max> $bodyLength */
        $bodyLength = $fileSize;
        $result = [
            'frontMatter' => $frontMatter,
            'bodyOffset' => 0,
            'bodyLength' => $bodyLength,
        ];

        return $result;
    }

    /**
     * @param resource $handle
     * @param array{frontMatter: array<string, mixed>, bodyOffset: int<0, max>, bodyLength: int<0, max>} $result
     * @return array{frontMatter: array<string, mixed>, bodyOffset: int<0, max>, bodyLength: int<0, max>}
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
                    /** @var int<0, max> $newOffset */
                    $result['bodyLength'] -= ($newOffset - $result['bodyOffset']);
                    $result['bodyOffset'] = $newOffset;
                    /** @var array{frontMatter: array<string, mixed>, bodyOffset: int<0, max>, bodyLength: int<0, max>} $result */
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

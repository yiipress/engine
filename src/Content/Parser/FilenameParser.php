<?php

declare(strict_types=1);

namespace App\Content\Parser;

use DateTimeImmutable;

final class FilenameParser
{
    private const string DATE_PATTERN = '/^(\d{4}-\d{2}-\d{2})-(.+)$/';

    /**
     * @return array{date: DateTimeImmutable|null, slug: string}
     */
    public function parse(string $filename): array
    {
        $name = basename($filename, '.md');

        if (preg_match(self::DATE_PATTERN, $name, $matches) === 1) {
            return [
                'date' => new DateTimeImmutable($matches[1]),
                'slug' => $matches[2],
            ];
        }

        return [
            'date' => null,
            'slug' => $name,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\I18nConfig;

final class PermalinkResolver
{
    public static function resolve(Entry $entry, Collection $collection, ?I18nConfig $i18n = null): string
    {
        if ($entry->permalink !== '') {
            return $entry->permalink;
        }

        $pattern = $collection->permalink;

        $replacements = [
            ':collection' => $collection->name,
            ':slug' => $entry->slug,
        ];

        if ($entry->date !== null) {
            $replacements[':year'] = $entry->date->format('Y');
            $replacements[':month'] = $entry->date->format('m');
            $replacements[':day'] = $entry->date->format('d');
        }

        $permalink = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $pattern,
        );

        return self::applyLanguagePrefix($permalink, $entry->language, $i18n);
    }

    public static function applyLanguagePrefix(string $permalink, string $language, ?I18nConfig $i18n): string
    {
        if ($i18n === null || $language === '' || !$i18n->isKnown($language) || $i18n->isDefault($language)) {
            return $permalink;
        }

        $prefix = '/' . $language;
        if ($permalink === '' || $permalink === '/') {
            return $prefix . '/';
        }
        if (str_starts_with($permalink, $prefix . '/')) {
            return $permalink;
        }
        return $prefix . $permalink;
    }
}

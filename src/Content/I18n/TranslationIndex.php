<?php

declare(strict_types=1);

namespace App\Content\I18n;

use App\Content\Model\Entry;
use App\Content\Model\I18nConfig;
use App\Content\Model\Translation;

use function hash;

/**
 * Pre-computed map of entry file path to its translations (alternate language versions).
 *
 * Two entries are considered translations of each other when they share the same
 * translation key. The translation key is either the explicit `translation_key` front
 * matter field or, when absent, the entry slug. Grouping is scoped per collection so
 * that entries in different collections with the same slug are not mistakenly linked.
 */
final class TranslationIndex
{
    /** @var array<string, list<Translation>> */
    private array $byFilePath = [];

    private string $signature = '';

    /**
     * @param list<array{entry: Entry, permalink: string}> $entries
     */
    public function __construct(array $entries, private readonly I18nConfig $i18n)
    {
        $this->build($entries);
    }

    /**
     * @return list<Translation>
     */
    public function forEntry(string $filePath): array
    {
        return $this->byFilePath[$filePath] ?? [];
    }

    public function signature(): string
    {
        return $this->signature;
    }

    /**
     * @param list<array{entry: Entry, permalink: string}> $entries
     */
    private function build(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $groups = [];
        foreach ($entries as $task) {
            $entry = $task['entry'];
            if (!$this->i18n->isKnown($entry->language) && !$this->i18n->isDefault($entry->language)) {
                continue;
            }
            $key = $entry->collection . ':' . ($entry->translationKey !== '' ? $entry->translationKey : $entry->slug);
            $language = $entry->language !== '' ? $entry->language : $this->i18n->defaultLanguage;
            $groups[$key][$language] = [
                'filePath' => $entry->filePath,
                'permalink' => $task['permalink'],
                'title' => $entry->title,
            ];
        }

        $signatureParts = [];
        foreach ($groups as $variants) {
            if (count($variants) < 2) {
                continue;
            }
            foreach ($variants as $sourceLanguage => $source) {
                $alternates = [];
                foreach ($variants as $language => $target) {
                    if ($language === $sourceLanguage) {
                        continue;
                    }
                    $alternates[] = new Translation(
                        language: $language,
                        permalink: $target['permalink'],
                        title: $target['title'],
                    );
                    $signatureParts[] = $source['filePath'] . '|' . $language . '|' . $target['permalink'];
                }
                if ($alternates !== []) {
                    $this->byFilePath[$source['filePath']] = $alternates;
                }
            }
        }

        $this->signature = hash('xxh128', implode("\n", $signatureParts));
    }
}

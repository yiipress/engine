<?php

declare(strict_types=1);

namespace App\Content\Model;

use function in_array;

final readonly class I18nConfig
{
    /**
     * @param list<string> $languages ISO codes enabled for the site; the default language must be included.
     */
    public function __construct(
        public array $languages,
        public string $defaultLanguage,
    ) {}

    public function isKnown(string $language): bool
    {
        return $language !== '' && in_array($language, $this->languages, true);
    }

    public function isDefault(string $language): bool
    {
        return $language === '' || $language === $this->defaultLanguage;
    }
}

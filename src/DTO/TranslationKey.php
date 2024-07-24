<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\DTO;

class TranslationKey
{
    public function __construct(
        public readonly int $keyId,
        public readonly string $key,
        /** @var Translation[] */
        private readonly array $translations,
    ) {}

    public function getTranslationForLocale(string $locale): ?Translation
    {
        $translations = array_filter($this->translations, fn (Translation $translation) => $translation->locale === $locale);

        if (count($translations) === 0) {
            return null;
        }

        return $translations[array_key_first($translations)];
    }
}

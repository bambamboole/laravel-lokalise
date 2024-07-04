<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Bambamboole\LaravelLokalise\DTO\Translation;
use Bambamboole\LaravelLokalise\DTO\TranslationKey;
use Illuminate\Support\Str;

class TranslationKeyFactory
{
    public function createFromLokalise(array $data): TranslationKey
    {
        $key = Str::replace('::', '.', $data['key_name']['web']);
        $translations = array_filter(
            array_map(
                fn (array $translation) => $this->prepareTranslation($translation['language_iso'], $translation['translation']),
                $data['translations'],
            ),
        );

        return new TranslationKey($key, $translations);
    }

    private function prepareTranslation(string $locale, ?string $translation = null): ?Translation
    {
        if (empty($translation)) {
            return null;
        }

        // Check if the translation is a plural translation and map it to a Laravel compatible format
        $json = json_decode($translation, true);
        if ($json && isset($json['one'], $json['other'])) {
            if (empty($json['one']) && empty($json['other'])) {
                return null;
            }
            $translation = $json['one'].'|'.$json['other'];
        }
        // I get these strings and need to convert it to colon prefix variable names:
        // The [%1$s:attribute] field must be present when [%1$s:values] are present.
        //The :attribute field must be present when :values are present.
        $translation = Str::of($translation)->replaceMatches('/\[\%1\$s:(\w+)\]/', ':$1')->__toString();

        return new Translation($locale, $translation);
    }
}

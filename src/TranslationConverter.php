<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Illuminate\Support\Str;

class TranslationConverter
{
    public static function toLokalise(string $translation): string
    {
        // For translation values, we need more sophisticated pattern matching
        // This regex avoids replacing:
        // 1. Variables already in curly braces like {VARIABLE}
        // 2. Variables inside HTML attributes like style="width:100%"
        $translationWithReplacedVariableSyntax = preg_replace(
            '/(?<![\{\w]):([\w\d]+)(?!\}|%|[^\s\.,;!\?<>\(\)\[\]\{\}\'"])/m',
            '{{$1}}',
            $translation
        );

        if (Str::contains($translationWithReplacedVariableSyntax, '|')) {
            [$singular, $plural] = explode('|', $translationWithReplacedVariableSyntax, 2);
            $translationWithReplacedVariableSyntax = json_encode([
                'one' => $singular,
                'other' => $plural,
            ], JSON_UNESCAPED_UNICODE);
        }

        return $translationWithReplacedVariableSyntax;
    }

    public static function fromLokalise(string $translation): string
    {
        // Check if the translation is a plural translation and map it to a Laravel compatible format
        $json = json_decode($translation, true);
        if ($json && isset($json['one'], $json['other'])) {
            $translation = $json['one'].'|'.$json['other'];
        }

        // I get these strings and need to convert it to colon prefix variable names:
        // The [%1$s:attribute] field must be present when [%1$s:values] are present.
        // The :attribute field must be present when :values are present.
        return Str::of($translation)->replaceMatches('/\[\%1\$s:(\w+)\]/', ':$1')->__toString();
    }
}

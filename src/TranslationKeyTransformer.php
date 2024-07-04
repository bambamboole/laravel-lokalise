<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

class TranslationKeyTransformer
{
    public static function transformDottedToNested(array $keysWithTranslations): array
    {
        $result = [];

        foreach ($keysWithTranslations as $dottedString => $translation) {
            $keys = explode('.', $dottedString);
            $current = &$result;

            foreach ($keys as $key) {
                // If the current key already exists as a value, skip this dotted string
                if (isset($current[$key]) && ! is_array($current[$key])) {
                    continue 2;
                }
                $current = &$current[$key];
            }

            // Reset reference to root of result array
            $current = &$result;
            while (count($keys) > 1) {
                $key = array_shift($keys);
                if (! isset($current[$key])) {
                    $current[$key] = [];
                } elseif (! is_array($current[$key])) {
                    // If the current key is set as a value, skip this dotted string
                    continue 2;
                }
                $current = &$current[$key];
            }

            $lastKey = array_shift($keys);
            if (! isset($current[$lastKey])) {
                $current[$lastKey] = $translation;
            } elseif (is_array($current[$lastKey])) {
                // If the current key is an array, skip this dotted string
                continue;
            }
        }

        return $result;
    }
}

<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

class TranslationKeyTransformer
{
    public static function transformDottedToNested(array $keysWithTranslations): array
    {
        $result = [];

        foreach ($keysWithTranslations as $dottedString => $translation) {
            try {
                $keys = explode('.', $dottedString);

            }catch (\TypeError $e) {
dump($dottedString, $keysWithTranslations);
                throw $e;
            }
            $current = &$result;

            // Check for invalid keys
            foreach ($keys as $key) {
                if (isset($current[$key]) && ! is_array($current[$key])) {
                    // If current key is set as a value, skip invalid nested key
                    continue 2;
                }
                $current = &$current[$key];
            }

            $current = &$result;
            while (count($keys) > 1) {
                $key = array_shift($keys);
                if (! isset($current[$key])) {
                    $current[$key] = [];
                } elseif (! is_array($current[$key])) {
                    // If current key is set as a value, skip invalid nested key
                    continue 2;
                }
                $current = &$current[$key];
            }

            $lastKey = array_shift($keys);
            if (! isset($current[$lastKey])) {
                $current[$lastKey] = $translation;
            } elseif (is_array($current[$lastKey])) {
                // If the current key is an array, skip invalid key
                continue;
            }
        }

        return $result;
    }
}

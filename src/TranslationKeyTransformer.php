<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Illuminate\Support\Str;

class TranslationKeyTransformer
{
    private array $skipped = [];

    public function transformDottedToNested(array $keysWithTranslations): array
    {
        $result = [];

        foreach ($keysWithTranslations as $dottedString => $translation) {
            $keys = explode('.', $dottedString);
            $current = &$result;

            foreach ($keys as $key) {
                // If the current key already exists as a value, skip this dotted string
                if (isset($current[$key]) && ! is_array($current[$key])) {
                    $this->skipped[] = [
                        'key' => $dottedString,
                        'value' => $translation,
                        'reason' => Str::beforeLast($dottedString, '.').' already exists as a leaf node',
                    ];

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
                    $this->skipped[] = [
                        'key' => $dottedString,
                        'value' => $translation,
                        'reason' => Str::beforeLast($dottedString, '.').' already exists as a leaf node',
                    ];

                    // If the current key is set as a value, skip this dotted string
                    continue 2;
                }
                $current = &$current[$key];
            }

            $lastKey = array_shift($keys);
            if (! isset($current[$lastKey])) {
                $current[$lastKey] = $translation;
            } else {
                $this->skipped[] = [
                    'key' => $dottedString,
                    'value' => $translation,
                    'reason' => Str::beforeLast($dottedString, '.').' already exists as a leaf node',
                ];
            }
        }

        return $result;
    }

    public function getSkipped(bool $reset = true): array
    {
        $skipped = $this->skipped;
        if ($reset) {
            $this->skipped = [];
        }

        return $skipped;
    }
}

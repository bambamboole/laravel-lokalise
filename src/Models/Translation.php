<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Models;

use Bambamboole\LaravelTranslationDumper\TranslationIdentifier;
use Bambamboole\LaravelTranslationDumper\TranslationType;
use Illuminate\Support\Str;

class Translation
{
    public readonly TranslationType $type;

    public function __construct(public readonly string $locale, public readonly string $key, public readonly string $value)
    {
        $this->type = TranslationIdentifier::identify($this->key);
    }

    public function getFilename(): string
    {
        return match ($this->type) {
            // namespaced or dotted keys are located in PHP files
            TranslationType::PHP => $this->locale.'/'.Str::before($this->key, '.').'.php',
            // global keys are located in a single JSON file
            TranslationType::JSON => $this->locale.'.json',
        };
    }

    public function keyInFile(): string
    {
        return match ($this->type) {
            // we will remove the first segment since this defined by the file name.
            TranslationType::PHP => Str::after($this->key, '.'),
            TranslationType::JSON => $this->key,
        };
    }
}

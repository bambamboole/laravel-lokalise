<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Models;

use Bambamboole\LaravelTranslationDumper\TranslationType;
use Illuminate\Support\Str;

class TranslationFile
{
    public function __construct(public readonly \SplFileInfo $file) {}

    public function type(): TranslationType
    {
        return match ($this->file->getExtension()) {
            'php' => TranslationType::PHP,
            'json' => TranslationType::JSON,
            default => throw new \Exception('File type not supported'),
        };
    }

    public function locale(): string
    {
        $relativePath = Str::afterLast($this->file->getPathname(), '/lang/');

        return match ($this->type()) {
            TranslationType::PHP => Str::before($relativePath, '/'),
            TranslationType::JSON => Str::before($relativePath, '.json'),
        };
    }
}

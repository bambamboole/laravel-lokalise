<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Bambamboole\LaravelLokalise\DTO\TranslationFile;
use Bambamboole\LaravelTranslationDumper\TranslationType;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LocalTranslationRepository
{
    private string $langPath;

    public function __construct(
        private readonly Filesystem $fs,
        private readonly string $basePath,
    ) {
        $this->langPath = is_dir($dir = $this->basePath.'/resources/lang') ? $dir : $this->basePath.'/lang';
    }

    public function getLocales(): array
    {
        $localeFolders = array_map(
            fn (string $path) => Str::afterLast($path, '/'),
            $this->fs->directories($this->langPath),
        );

        $jsonFileLocales = array_map(
            fn (\SplFileInfo $file) => Str::before($file->getFilename(), '.json'),
            array_filter(
                $this->fs->files($this->langPath),
                fn (\SplFileInfo $file) => $file->getExtension() === 'json',
            )
        );

        return array_unique(array_merge($localeFolders, $jsonFileLocales));
    }

    /** @return TranslationFile[] */
    public function getTranslationFiles(?string $locale = null, ?TranslationType $type = null): array
    {
        if (! $this->fs->isDirectory($this->langPath)) {
            return [];
        }

        $files = array_map(fn (\SplFileInfo $file) => new TranslationFile($file), $this->fs->allFiles($this->langPath));
        if ($locale) {
            $files = array_filter($files, fn (TranslationFile $file) => $file->locale() === $locale);
        }
        if ($type) {
            $files = array_filter($files, fn (TranslationFile $file) => $file->type() === $type);
        }

        return $files;
    }

    public function getTranslations(TranslationFile $file): array
    {
        if ($file->type() === TranslationType::PHP) {
            $group = Str::before($file->file->getFilename(), '.php');
            $translations = require $file->file->getRealPath();

            return Arr::dot($translations, $group.'.');
        }

        return Arr::dot(json_decode($this->fs->get($file->file->getRealPath()), true));
    }
}

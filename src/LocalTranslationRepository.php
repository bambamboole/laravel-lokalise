<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Bambamboole\LaravelLokalise\Models\Translation;
use Bambamboole\LaravelLokalise\Models\TranslationFile;
use Bambamboole\LaravelTranslationDumper\ArrayExporter;
use Bambamboole\LaravelTranslationDumper\TranslationType;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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

    public function getTranslations(TranslationFile|string $file): array
    {
        if (is_string($file)) {
            $file = new TranslationFile(new \SplFileInfo($this->langPath.'/'.$file));
        }
        if ($file->type() === TranslationType::PHP) {
            $group = Str::before($file->file->getFilename(), '.php');
            $translations = require $file->file->getRealPath();

            return Arr::dot($translations, $group.'.');
        }

        return Arr::dot(json_decode($this->fs->get($file->file->getRealPath()), true));
    }

    /** @param Translation[] $translations */
    public function saveTranslations(Collection $translations): void
    {
        $locales = $translations->groupBy(fn (Translation $translation) => $translation->locale);

        foreach ($locales as $locale => $translations) {
            $files = $translations->groupBy(fn (Translation $translation) => $translation->getFilename());
            $phpFiles = $files->filter(fn ($_, string $key) => str_ends_with($key, '.php'));

            $skippedKeys = $phpFiles
                ->map(function (Collection $translations, string $file) {
                    $newTranslations = $translations
                        ->mapWithKeys(fn (Translation $translation) => [$translation->keyInFile() => $translation->value])
                        ->toArray();

                    $absolutePath = $this->langPath.'/'.$file;

                    $existingTranslations = $this->fs->exists($absolutePath)
                        ? Arr::dot(require $absolutePath)
                        : [];
                    $merged = array_merge($existingTranslations, $newTranslations);
                    // We sort by key, so that the nesting behaves the same every time
                    ksort($merged);
                    $nested = Arr::undot($merged);
                    $this->fs->put($absolutePath, (new ArrayExporter)->export($nested));
                    $dotted = Arr::dot($nested);
                    $skippedKeys = [];
                    $group = Str::between($file, '/', '.php');
                    foreach ($merged as $key => $value) {
                        ! isset($dotted[$key]) && $skippedKeys[$group.'.'.$key] = $value;
                    }

                    return $skippedKeys;
                })
                ->flatMap(fn ($value) => $value)
                ->all();

            $absolutePath = $this->langPath.'/'.$locale.'.json';
            $existingTranslations = $this->fs->exists($absolutePath)
                ? json_decode($this->fs->get($absolutePath), true, JSON_UNESCAPED_UNICODE)
                : [];
            $jsonFileTranslations = $files->first(fn ($_, string $key) => str_ends_with($key, '.json'));

            $newTranslations = $jsonFileTranslations ? $jsonFileTranslations
                ->mapWithKeys(fn (Translation $translation) => [$translation->keyInFile() => $translation->value])
                ->toArray()
                : [];

            $merged = array_merge($existingTranslations, $newTranslations, $skippedKeys);
            ksort($merged);

            $content = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
            $this->fs->put($absolutePath, $content);
        }
    }
}

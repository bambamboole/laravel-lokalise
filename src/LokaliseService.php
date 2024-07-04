<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Bambamboole\LaravelTranslationDumper\ArrayExporter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class LokaliseService
{
    public function __construct(
        private LokaliseClient $client,
        private TranslationKeyTransformer $keyTransformer,
        private Filesystem $fs,
        private string $langPath,
        private string $basePath,
    ) {}

    public function downloadTranslations(): DownloadReport
    {
        $report = new DownloadReport();
        $keys = $this->client->getKeys();

        $dottedKeys = array_filter($keys, fn ($key) => ! Str::contains($key, ' '), ARRAY_FILTER_USE_KEY);
        $groupedKeys = [];
        foreach ($dottedKeys as $key => $translations) {
            $groupedKeys[Str::before($key, '.')][$key] = $translations;
        }
        $nonDottedKeys = array_filter($keys, fn ($key) => Str::contains($key, ' '), ARRAY_FILTER_USE_KEY);
        $report->addKeyCount(count($dottedKeys), count($nonDottedKeys));

        foreach ($this->client->getLocales() as $locale) {
            $this->writePhpFiles($locale, $groupedKeys);
            $this->writeJsonFile($locale, $nonDottedKeys);
            $report->addLocaleReport(new LocaleReport($locale, $this->keyTransformer->getSkipped()));
        }

        return $report;
    }

    private function writeJsonFile(string $locale, array $keys): void
    {
        $translations = [];
        foreach ($keys as $key => $translationsForKey) {
            if (! isset($translationsForKey[$locale]) || $this->isEmptyTranslation($translationsForKey[$locale])) {
                continue;
            }
            $translations[$key] = $this->replacePlaceholders($translationsForKey[$locale]);
        }
        if (empty($translations)) {
            return;
        }
        $path = sprintf('%s/%s.json', $this->langPath, $locale);
        $beautifiedTranslations = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
        $this->fs->ensureDirectoryExists(Str::beforeLast($path, '/'));
        $this->fs->put($path, $beautifiedTranslations);
    }

    private function writePhpFiles(string $locale, array $groupedKeys): void
    {
        foreach ($groupedKeys as $group => $keys) {
            $translations = [];
            foreach ($keys as $key => $translationsForKey) {
                if (! isset($translationsForKey[$locale]) || $this->isEmptyTranslation($translationsForKey[$locale])) {
                    continue;
                }
                $translations[$key] = $this->replacePlaceholders($translationsForKey[$locale]);
            }
            if (empty($translations)) {
                continue;
            }
            $translations = $this->keyTransformer->transformDottedToNested($translations);
            $path = sprintf('%s/%s/%s.php', $this->langPath, $locale, $group);
            $translations = $translations[$group];
            $beautifiedTranslations = (new ArrayExporter)->export($translations);
            $this->fs->ensureDirectoryExists(Str::beforeLast($path, '/'));
            $this->fs->put($path, $beautifiedTranslations);
        }
    }

    public function uploadTranslations(): void
    {
        foreach ($this->client->getLocales() as $locale) {
            $this->uploadJsonFileIfExists($locale);
            $this->uploadPhpFiles($locale);
        }
    }

    private function uploadJsonFileIfExists(string $locale): void
    {
        $filePath = $this->langPath.'/'.$locale.'.json';
        if ($this->fs->missing($filePath)) {
            return;
        }
        $translations = json_decode($this->fs->get($filePath), true);
        $translations = $this->prepare($translations);
        $this->client->uploadFile(
            json_encode($translations),
            ltrim(str_replace($this->basePath, '', $filePath), '/'),
            $locale,
        );
    }

    private function uploadPhpFiles(string $locale): void
    {
        $localePath = $this->langPath.'/'.$locale;
        if (! $this->fs->isDirectory($localePath)) {
            return;
        }
        $phpFiles = array_filter($this->fs->files($localePath), fn (\SplFileInfo $file) => $file->getExtension() === 'php');

        foreach ($phpFiles as $file) {
            $absoluteFilePath = $localePath.'/'.$file->getBasename();
            $group = $file->getBasename('.php');
            $translations = require $absoluteFilePath;
            $translations = $this->prepare([$group => $translations]);

            $this->client->uploadFile(
                json_encode($translations),
                ltrim(str_replace($this->basePath, '', $absoluteFilePath), '/'),
                $locale,
            );
        }
    }

    private function prepare(array $translations): array
    {
        $lokaliseTranslations = [];
        $keys = array_keys($translations);

        for ($i = 0; $i < count($keys); $i++) {
            $laravelKey = $keys[$i];
            $i18nKey = preg_replace("/:([\w\d]+)/", '{{$1}}', $laravelKey);
            $laravelValue = $translations[$laravelKey];

            if (is_array($laravelValue)) {
                $lokaliseTranslations[$i18nKey] = $this->prepare($laravelValue);
            } else {
                $translationWithReplacedVariableSyntax = preg_replace("/:([\w\d]+)/", '{{$1}}', $laravelValue);
                if (Str::contains($translationWithReplacedVariableSyntax, '|')) {
                    [$singular, $plural] = explode('|', $translationWithReplacedVariableSyntax);
                    $translationWithReplacedVariableSyntax = json_encode([
                        'one' => $singular,
                        'other' => $plural,
                    ]);
                }
                $lokaliseTranslations[$i18nKey] = $translationWithReplacedVariableSyntax;
            }
        }

        return $lokaliseTranslations;
    }

    private function replacePlaceholders(string $translation): string
    {
        $json = json_decode($translation, true);
        if ($json && isset($json['one'], $json['other'])) {
            $translation = $json['one'].'|'.$json['other'];
        }
        // I get these strings and need to convert it to colon prefix variable names:
        // The [%1$s:attribute] field must be present when [%1$s:values] are present.
        //The :attribute field must be present when :values are present.
        $replaced = Str::of($translation)->replaceMatches('/\[\%1\$s:(\w+)\]/', ':$1')->__toString();

        return $replaced;
    }

    private function isEmptyTranslation(string $translation): bool
    {
        if (empty($translation)) {
            return true;
        }
        $json = json_decode($translation, true);
        if ($json && isset($json['one'], $json['other']) && empty($json['one']) && empty($json['other'])) {
            return true;
        }

        return false;
    }
}

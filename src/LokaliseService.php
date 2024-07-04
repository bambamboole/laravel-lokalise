<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Bambamboole\LaravelLokalise\DTO\DownloadReport;
use Bambamboole\LaravelLokalise\DTO\LocaleReport;
use Bambamboole\LaravelLokalise\DTO\TranslationKey;
use Bambamboole\LaravelTranslationDumper\ArrayExporter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class LokaliseService
{
    public function __construct(
        private readonly LokaliseClient $client,
        private readonly TranslationKeyTransformer $keyTransformer,
        private readonly Filesystem $fs,
        private readonly string $langPath,
        private readonly string $basePath,
    ) {}

    public function downloadTranslations(): DownloadReport
    {
        $report = new DownloadReport();
        $keys = $this->client->getKeys();

        $dottedKeys = array_filter($keys, fn (TranslationKey $key) => ! Str::contains($key->key, ' '));
        $groupedKeys = [];
        foreach ($dottedKeys as $key) {
            $groupedKeys[Str::before($key->key, '.')][] = $key;
        }
        $nonDottedKeys = array_filter($keys, fn (TranslationKey $key) => Str::contains($key->key, ' '));
        $report->addKeyCount(count($dottedKeys), count($nonDottedKeys));

        foreach ($this->client->getLocales() as $locale) {
            $this->writePhpFiles($locale, $groupedKeys);
            $this->writeJsonFile($locale, $nonDottedKeys);
            $report->addLocaleReport(new LocaleReport($locale, $this->keyTransformer->getSkipped()));
        }

        return $report;
    }

    private function writePhpFiles(string $locale, array $groupedKeys): void
    {
        foreach ($groupedKeys as $group => $keys) {
            $translations = [];
            foreach ($keys as $key) {
                /** @var TranslationKey $key */
                $translation = $key->getTranslationForLocale($locale);
                if (! $translation) {
                    continue;
                }
                $translations[$key->key] = $translation->value;
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

    private function writeJsonFile(string $locale, array $keys): void
    {
        foreach ($keys as $key) {
            /** @var TranslationKey $key */
            $translation = $key->getTranslationForLocale($locale);
            if (! $translation) {
                continue;
            }
            $translations[$key->key] = $translation->value;
        }
        if (empty($translations)) {
            return;
        }
        $path = sprintf('%s/%s.json', $this->langPath, $locale);
        $beautifiedTranslations = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
        $this->fs->ensureDirectoryExists(Str::beforeLast($path, '/'));
        $this->fs->put($path, $beautifiedTranslations);
    }

    public function uploadTranslations(): void
    {
        foreach ($this->client->getLocales() as $locale) {
            $this->uploadJsonFile($locale);
            $this->uploadPhpFiles($locale);
        }
    }

    private function uploadJsonFile(string $locale): void
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
}

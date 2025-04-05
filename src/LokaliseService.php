<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Bambamboole\LaravelLokalise\DTO\DownloadReport;
use Bambamboole\LaravelLokalise\DTO\LocaleReport;
use Bambamboole\LaravelLokalise\DTO\TranslationFile;
use Bambamboole\LaravelLokalise\DTO\TranslationKey;
use Bambamboole\LaravelTranslationDumper\ArrayExporter;
use Bambamboole\LaravelTranslationDumper\TranslationIdentifier;
use Bambamboole\LaravelTranslationDumper\TranslationType;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class LokaliseService
{
    private string $langPath;

    public function __construct(
        private readonly LokaliseClient $client,
        private readonly TranslationKeyTransformer $keyTransformer,
        private readonly Filesystem $fs,
        private readonly LocalTranslationRepository $repository,
        private readonly string $basePath,
    ) {
        $this->langPath = is_dir($dir = $this->basePath.'/resources/lang') ? $dir : $this->basePath.'/lang';
    }

    public function downloadTranslations(): DownloadReport
    {
        $report = new DownloadReport;
        $keys = $this->client->getKeys();
        $report->addLokaliseKeyCount(count($keys));

        $dottedKeys = [];
        $nonDottedKeys = [];
        foreach ($keys as $key) {
            match (TranslationIdentifier::identify($key->key)) {
                TranslationType::PHP => $dottedKeys[] = $key,
                TranslationType::JSON => $nonDottedKeys[] = $key,
            };
        }
        $groupedKeys = [];
        foreach ($dottedKeys as $key) {
            $groupedKeys[Str::before($key->key, '.')][] = $key;
        }

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

    public function uploadTranslations(bool $cleanup = true, bool $force = false): void
    {
        $locales = $this->client->getLocales();

        foreach ($locales as $locale) {
            $files = $this->repository->getTranslationFiles($locale);
            foreach ($files as $file) {
                $this->uploadFile($file, $cleanup, $force);
            }
        }
        if ($cleanup === true) {
            $this->cleanupFiles($locales);
        }
    }

    public function uploadSpecificFiles(array $files, bool $cleanup = true, bool $force = true): void
    {
        $translationFiles = $this->repository->getTranslationFiles();
        $foundFiles = array_filter($translationFiles, fn (TranslationFile $tf) => in_array($tf->file->getRealPath(), $files));
        foreach ($foundFiles as $file) {
            $this->uploadFile($file, $cleanup, $force);
        }
    }

    private function uploadFile(TranslationFile $file, bool $cleanup, bool $force): void
    {
        $translations = $this->repository->getTranslations($file);
        $translations = $this->prepare($translations);

        $this->client->uploadFile(
            json_encode($translations, JSON_UNESCAPED_UNICODE),
            ltrim(str_replace($this->basePath, '', $file->file->getRealPath()), '/'),
            $file->locale(),
            $cleanup,
            $force
        );
    }

    private function cleanupFiles(array $locales): void
    {
        $fileNames = array_unique(
            array_map(
                fn (TranslationFile $file) => $file->file->getFilename(),
                $this->repository->getTranslationFiles(type: TranslationType::PHP),
            )
        );
        foreach ($locales as $locale) {
            $localPhpFiles = array_unique(array_merge($localPhpFiles, $this->repo($locale)));
        }
        $remotePhpFiles = $this->getRemotePhpFiles();

        $filesToDelete = array_filter(
            $remotePhpFiles,
            fn (string $file) => ! in_array(Str::afterLast($file, '/'), $fileNames, true),
        );

        // Lokalise doesn't let us just delete the file and all referenced keys. We have to delete each key individually.
        $keysToDelete = [];
        foreach ($filesToDelete as $file) {
            $keysToDelete = array_merge($keysToDelete, $this->client->getKeys($file));
        }

        if (empty($keysToDelete)) {
            return;
        }

        $this->client->deleteKeys($keysToDelete);
    }

    private function prepare(array $translations): array
    {
        $lokaliseTranslations = [];

        foreach ($translations as $key => $value) {
            $lokaliseKey = preg_replace("/:([\w\d]+)/", '{{$1}}', $key);
            $translationWithReplacedVariableSyntax = preg_replace("/:([\w\d]+)/", '{{$1}}', $value);
            if (Str::contains($translationWithReplacedVariableSyntax, '|')) {
                [$singular, $plural] = explode('|', $translationWithReplacedVariableSyntax);
                $translationWithReplacedVariableSyntax = json_encode([
                    'one' => $singular,
                    'other' => $plural,
                ], JSON_UNESCAPED_UNICODE);
            }
            $lokaliseTranslations[$lokaliseKey] = $translationWithReplacedVariableSyntax;
        }

        return $lokaliseTranslations;
    }

    private function getRemotePhpFiles(): array
    {
        $remotePhpFiles = array_filter(
            $this->client->getFiles(),
            fn (string $file) => Str::endsWith($file, '.php'),
        );

        return array_values($remotePhpFiles);
    }
}

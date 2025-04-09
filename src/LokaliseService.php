<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Bambamboole\LaravelLokalise\Commands\DownloadTranslationFilesCommand;
use Bambamboole\LaravelLokalise\Models\Translation;
use Bambamboole\LaravelLokalise\Models\TranslationFile;
use Bambamboole\LaravelTranslationDumper\TranslationType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LokaliseService
{
    public function __construct(
        private readonly LokaliseClient $client,
        private readonly LocalTranslationRepository $repository,
        private readonly string $basePath,
        private readonly bool $skipJsonFiles = true,
    ) {}

    public function downloadTranslations(DownloadTranslationFilesCommand $command): void
    {
        $command->getComponents()->info('Download translation...');

        $translations = $this->client
            ->withProgressbar($command->getOutput()->createProgressBar())
            ->getTranslations();
        $command->getComponents()->info(sprintf('%s translations downloaded', $translations->count()));

        $this->repository->saveTranslations($translations);
    }

    public function uploadTranslations(bool $cleanup = true, bool $replace = false): void
    {
        $locales = $this->client->getLocales();

        foreach ($locales as $locale) {
            $files = $this->repository->getTranslationFiles($locale, $this->skipJsonFiles ? TranslationType::PHP : null);
            foreach ($files as $file) {
                $this->uploadFile($file, $cleanup, $replace);
            }
        }
        if ($cleanup === true) {
            $this->cleanupFiles();
        }
    }

    public function uploadSpecificFiles(array $files, bool $cleanup = true, bool $replace = true): void
    {
        $translationFiles = $this->repository->getTranslationFiles(type: $this->skipJsonFiles ? TranslationType::PHP : null);
        $foundFiles = array_filter($translationFiles, fn (TranslationFile $tf) => in_array($tf->file->getRealPath(), $files));
        foreach ($foundFiles as $file) {
            $this->uploadFile($file, $cleanup, $replace);
        }
    }

    private function uploadFile(TranslationFile $file, bool $cleanup, bool $replace): void
    {
        $translations = $this->repository->getTranslations($file);
        $translations = $this->prepare($translations);

        $this->client->uploadFile(
            json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ltrim(str_replace($this->basePath, '', $file->file->getRealPath()), '/'),
            $file->locale(),
            $cleanup,
            $replace
        );
    }

    private function cleanupFiles(): void
    {
        $fileNames = array_unique(
            array_map(
                fn (TranslationFile $file) => $file->file->getFilename(),
                $this->repository->getTranslationFiles(type: TranslationType::PHP),
            )
        );

        // Lokalise doesn't let us just delete the file and all referenced keys. We have to delete each key individually.
        $keysToDelete = $this->getRemotePhpFiles()
            ->filter(fn (string $file) => ! in_array(Str::afterLast($file, '/'), $fileNames, true))
            ->map(fn (string $file) => $this->client->getTranslations($file)->map(fn (Translation $translation) => $translation->key))
            ->flatten()
            ->unique();

        if ($keysToDelete->isEmpty()) {
            return;
        }

        $this->client->deleteKeys($keysToDelete->all());
    }

    public function prepare(array $translations): array
    {
        $lokaliseTranslations = [];

        foreach ($translations as $key => $value) {
            if (is_array($value) && empty($value)) {
                continue;
            }
            // For keys, we can use the simple regex
            $lokaliseKey = preg_replace("/:([\w\d]+)/", '{{$1}}', $key);

            $lokaliseTranslations[$lokaliseKey] = TranslationConverter::toLokalise($value);
        }

        return $lokaliseTranslations;
    }

    private function getRemotePhpFiles(): Collection
    {
        $remotePhpFiles = array_filter(
            $this->client->getFiles(),
            fn (string $file) => Str::endsWith($file, '.php'),
        );

        return collect(array_values($remotePhpFiles));
    }
}

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
            $files = $this->repository->getTranslationFiles($locale);
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
        $translationFiles = $this->repository->getTranslationFiles();
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
            json_encode($translations, JSON_UNESCAPED_UNICODE),
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

    private function getRemotePhpFiles(): Collection
    {
        $remotePhpFiles = array_filter(
            $this->client->getFiles(),
            fn (string $file) => Str::endsWith($file, '.php'),
        );

        return collect(array_values($remotePhpFiles));
    }
}

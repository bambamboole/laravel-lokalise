<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Commands;

use Bambamboole\LaravelLokalise\LocalTranslationRepository;
use Bambamboole\LaravelLokalise\LokaliseClient;
use Bambamboole\LaravelLokalise\LokaliseService;
use Bambamboole\LaravelLokalise\Models\TranslationFile;
use Illuminate\Console\Command;

class SyncFileCommand extends Command
{
    protected $signature = 'lokalise:sync {file}';

    protected $description = 'Sync local file in all locales to lokalise. Then download keys formt his file again.';

    public function handle(LocalTranslationRepository $repo, LokaliseService $lokaliseService, LokaliseClient $client): int
    {
        $files = $repo->getTranslationFiles();
        $files = collect($files)
            ->filter(fn (TranslationFile $file) => $file->file->getFilename() === $this->argument('file'))
            ->map(fn (TranslationFile $file) => $file->file->getRealPath())
            ->all();

        $lokaliseService->uploadSpecificFiles($files);

        // @TODO implement proper waiting for process form lokalise
        sleep(15);

        $translations = $client->getTranslations('resources/lang/%LANG_ISO%/'.$this->argument('file'));
        $repo->saveTranslations($translations);

        return self::SUCCESS;
    }
}

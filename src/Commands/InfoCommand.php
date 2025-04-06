<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Commands;

use Bambamboole\LaravelLokalise\LocalTranslationRepository;
use Bambamboole\LaravelLokalise\LokaliseClient;
use Bambamboole\LaravelLokalise\Models\Translation;
use Illuminate\Console\Command;

class InfoCommand extends Command
{
    protected $signature = 'translations:info';

    protected $description = 'Get information about your local and lokalise translations';

    public function handle(LokaliseClient $lokaliseClient, LocalTranslationRepository $repository): int
    {
        $localLocales = $repository->getLocales();
        $this->components->info(sprintf('Locales configured in locally: %s (%s)', count($localLocales), implode(',', $localLocales)));
        $lokaliseLocales = $lokaliseClient->getLocales();
        $this->components->info(sprintf('Locales configured in lokalise: %s (%s)', count($lokaliseLocales), implode(',', $lokaliseLocales)));

        $localFiles = $repository->getTranslationFiles();
        $this->components->info(sprintf('Local translation files: %s', count($localFiles)));
        $keys = collect();
        foreach ($localFiles as $file) {
            $keys = $keys->merge(array_keys($repository->getTranslations($file)))->unique();
        }
        $this->components->info(sprintf('Local unique translation keys: %s', $keys->count()));

        $lokaliseKeys = $lokaliseClient->getTranslations()->map(fn (Translation $t) => $t->key)->unique();
        $this->components->info(sprintf('Unique translation keys in Lokalise: %s', $lokaliseKeys->count()));
        $this->components->info(sprintf('Translations missing locally:  %s', $lokaliseKeys->diff($keys)->count()));
        $this->components->info(sprintf('Translations missing lokalise:  %s', $keys->diff($lokaliseKeys)->count()));

        return self::SUCCESS;
    }
}

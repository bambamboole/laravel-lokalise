<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Commands;

use Bambamboole\LaravelLokalise\DTO\TranslationKey;
use Bambamboole\LaravelLokalise\LocalTranslationRepository;
use Bambamboole\LaravelLokalise\LokaliseClient;
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
        $keys = [];
        foreach ($localFiles as $file) {
            $keys = array_unique(array_merge($keys, array_keys($repository->getTranslations($file))));
        }
        $this->components->info(sprintf('Local unique translation keys: %s', count($keys)));

        $lokaliseKeys = array_map(fn (TranslationKey $key) => $key->key, $lokaliseClient->getKeys(includeTranslations: false));
        $this->components->info('Translation keys in Lokalise: '.count($lokaliseKeys));
        $lokaliseKeys = array_unique($lokaliseKeys);
        $this->components->info('Unique translation keys in Lokalise: '.count($lokaliseKeys));

        $this->components->info('Translations missing locally: '.count(array_diff($lokaliseKeys, $keys)));
        $this->components->info('Translations missing in lokalise: '.count(array_diff($keys, $lokaliseKeys)));

        return self::SUCCESS;
    }
}

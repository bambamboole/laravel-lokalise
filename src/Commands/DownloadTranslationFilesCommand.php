<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Commands;

use Bambamboole\LaravelLokalise\LocalTranslationRepository;
use Bambamboole\LaravelLokalise\LokaliseClient;
use Illuminate\Console\Command;
use Illuminate\Console\View\Components\Factory;

class DownloadTranslationFilesCommand extends Command
{
    protected $signature = 'lokalise:download';

    protected $description = 'Download translations from Lokalise. This will overwrite existing files.';

    public function handle(LokaliseClient $client, LocalTranslationRepository $repo): int
    {
        $this->info('Download translations from Lokalise...');
        $this->components->info('Download translation...');

        $translations = $client
            ->withProgressbar($this->output->createProgressBar())
            ->getTranslations();
        $this->components->info(sprintf('%s translations downloaded', $translations->count()));

        $repo->saveTranslations($translations);

        return self::SUCCESS;
    }

    public function getComponents(): Factory
    {
        return $this->components;
    }
}

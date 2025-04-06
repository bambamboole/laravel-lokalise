<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Commands;

use Bambamboole\LaravelLokalise\LokaliseService;
use Illuminate\Console\Command;
use Illuminate\Console\View\Components\Factory;

class DownloadTranslationFilesCommand extends Command
{
    protected $signature = 'lokalise:download';

    protected $description = 'Download translations from Lokalise. This will overwrite existing files.';

    public function handle(LokaliseService $lokaliseService): int
    {
        $this->info('Download translations from Lokalise...');
        $lokaliseService->downloadTranslations($this);

        return self::SUCCESS;
    }

    public function getComponents(): Factory
    {
        return $this->components;
    }
}

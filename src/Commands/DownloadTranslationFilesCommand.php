<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Commands;

use Bambamboole\LaravelLokalise\LokaliseService;
use Illuminate\Console\Command;

class DownloadTranslationFilesCommand extends Command
{
    protected $signature = 'lokalise:download';

    protected $description = 'Download translations from Lokalise. This will overwrite existing files.';

    public function handle(LokaliseService $lokaliseService)
    {
        $lokaliseService->downloadTranslations();

        return self::SUCCESS;
    }
}

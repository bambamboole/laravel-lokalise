<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Commands;

use Bambamboole\LaravelLokalise\LokaliseService;
use Illuminate\Console\Command;

class UploadTranslationFilesCommand extends Command
{
    protected $signature = 'lokalise:upload {--cleanup}';

    protected $description = 'Upload translations to Lokalise. This will overwrite existing translations.';

    public function handle(LokaliseService $lokaliseService): int
    {
        $lokaliseService->uploadTranslations($this->option('cleanup'));

        return self::SUCCESS;
    }
}

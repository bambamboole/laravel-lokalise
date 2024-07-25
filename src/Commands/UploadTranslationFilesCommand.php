<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Commands;

use Bambamboole\LaravelLokalise\LokaliseService;
use Illuminate\Console\Command;

class UploadTranslationFilesCommand extends Command
{
    protected $signature = 'lokalise:upload {files?*} {--cleanup}';

    protected $description = 'Upload translations to Lokalise. This will overwrite existing translations.';

    public function handle(LokaliseService $lokaliseService): int
    {
        $files = $this->argument('files');

        empty($files)
            ? $lokaliseService->uploadTranslations($this->option('cleanup'))
            : $lokaliseService->uploadSpecificFiles($files, $this->option('cleanup'));

        return self::SUCCESS;
    }
}

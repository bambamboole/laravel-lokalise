<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Commands;

use Bambamboole\LaravelLokalise\LokaliseService;
use Illuminate\Console\Command;

class UploadTranslationFilesCommand extends Command
{
    protected $signature = 'lokalise:upload {files?*} {--cleanup} {--force}';

    protected $description = 'Upload translations to Lokalise. This will overwrite existing translations.';

    public function handle(LokaliseService $lokaliseService): int
    {
        $files = $this->argument('files');

        empty($files)
            ? $lokaliseService->uploadTranslations($this->option('cleanup'), $this->option('force'))
            : $lokaliseService->uploadSpecificFiles($files, $this->option('cleanup'), $this->option('force'));

        return self::SUCCESS;
    }
}

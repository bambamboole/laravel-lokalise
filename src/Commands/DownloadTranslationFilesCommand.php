<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Commands;

use Bambamboole\LaravelLokalise\LokaliseService;
use Illuminate\Console\Command;

class DownloadTranslationFilesCommand extends Command
{
    protected $signature = 'lokalise:download';

    protected $description = 'Download translations from Lokalise. This will overwrite existing files.';

    public function handle(LokaliseService $lokaliseService): int
    {
        $this->info('Download translations from Lokalise...');
        $report = $lokaliseService->downloadTranslations();
        $this->info('Downloaded '.$report->getKeyCount().' keys from Lokalise');
        $this->info('Dotted Keys    : '.$report->getDottedKeyCount());
        $this->info('Non dotted Keys: '.$report->getNonDottedKeyCount());
        foreach ($report->getLocaleReports() as $localeReport) {
            $this->info('Downloaded translations for locale '.$localeReport->locale);
            if (count($localeReport->skippedKeys) > 0) {
                $this->info('Skipped keys: '.count($localeReport->skippedKeys));
                $this->table(['key', 'value', 'reason'], $localeReport->skippedKeys);
            }
        }

        return self::SUCCESS;
    }
}

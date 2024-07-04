<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\DTO;

class DownloadReport
{
    private int $dottedKeyCount = 0;

    private int $nonDottedKeyCount = 0;

    /** @var LocaleReport[] */
    private array $localeReports = [];

    public function addKeyCount(int $dotted, int $nonDotted): void
    {
        $this->dottedKeyCount = $dotted;
        $this->nonDottedKeyCount = $nonDotted;
    }

    public function getKeyCount(): int
    {
        return $this->nonDottedKeyCount + $this->dottedKeyCount;
    }

    public function getDottedKeyCount(): int
    {
        return $this->dottedKeyCount;
    }

    public function getNonDottedKeyCount(): int
    {
        return $this->nonDottedKeyCount;
    }

    public function addLocaleReport(LocaleReport $report): void
    {
        $this->localeReports[] = $report;
    }

    public function getLocaleReports(): array
    {
        return $this->localeReports;
    }
}

<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

class LocaleReport
{
    public function __construct(public readonly string $locale, public readonly array $skippedKeys) {}
}

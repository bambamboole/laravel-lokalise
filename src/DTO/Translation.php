<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\DTO;

class Translation
{
    public function __construct(public readonly string $locale, public readonly string $value) {}
}

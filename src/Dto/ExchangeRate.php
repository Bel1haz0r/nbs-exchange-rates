<?php

namespace JustPhoenix\NbsExchangeRates\Dto;

final class ExchangeRate
{
    public function __construct(
        public readonly string $currencyCode,   // e.g. "EUR"
        public readonly int $currencyNumCode,    // e.g. 978
        public readonly int $unit,               // e.g. 1 or 100
        public readonly ?string $countryName,    // optional
        public readonly ?string $middle,         // decimal string
        public readonly ?string $buying,         // decimal string
        public readonly ?string $selling         // decimal string
    ) {}
}

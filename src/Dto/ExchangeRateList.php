<?php

namespace JustPhoenix\NbsExchangeRates\Dto;

final class ExchangeRateList
{
    /**
     * @param ExchangeRate[] $rates
     */
    public function __construct(
        public readonly \DateTimeImmutable $date,
        public readonly array $rates,
        public readonly ?string $listNumber = null,
        public readonly ?string $listType = null
    ) {}

    public function find(string $currencyCode): ?ExchangeRate
    {
        $currencyCode = strtoupper($currencyCode);
        foreach ($this->rates as $rate) {
            if ($rate->currencyCode === $currencyCode) {
                return $rate;
            }
        }
        return null;
    }
}

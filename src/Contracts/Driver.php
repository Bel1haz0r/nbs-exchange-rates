<?php

namespace JustPhoenix\NbsExchangeRates\Contracts;

use JustPhoenix\NbsExchangeRates\Enum\RateType;

interface Driver
{
    /**
     * Returns raw XML from NBS for the given date/rateType.
     */
    public function getRatesXmlByDate(\DateTimeInterface $date, RateType $rateType): string;

    /**
     * Returns raw XML for current list.
     */
    public function getCurrentRatesXml(RateType $rateType): string;
}

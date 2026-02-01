<?php

namespace JustPhoenix\NbsExchangeRates;

use JustPhoenix\NbsExchangeRates\Contracts\Driver;
use JustPhoenix\NbsExchangeRates\Dto\ExchangeRateList;
use JustPhoenix\NbsExchangeRates\Enum\RateType;
use JustPhoenix\NbsExchangeRates\Parser\NbsXmlParser;

final class NbsClient
{
    public function __construct(
        private readonly Driver $driver,
        private readonly NbsXmlParser $parser = new NbsXmlParser()
    ) {}

    public function getCurrent(RateType $rateType = RateType::MIDDLE): ExchangeRateList
    {
        $xml = $this->driver->getCurrentRatesXml($rateType);
        return $this->parser->parseExchangeRates($xml);
    }

    public function getByDate(\DateTimeInterface $date, RateType $rateType = RateType::MIDDLE): ExchangeRateList
    {
        $xml = $this->driver->getRatesXmlByDate($date, $rateType);
        return $this->parser->parseExchangeRates($xml);
    }
}

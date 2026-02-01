<?php

namespace JustPhoenix\NbsExchangeRates\Driver;

use JustPhoenix\NbsExchangeRates\Contracts\Driver;
use JustPhoenix\NbsExchangeRates\Enum\RateType;
use JustPhoenix\NbsExchangeRates\Exception\TransportException;

final class SoapXmlDriver implements Driver
{
    private \SoapClient $client;

    /**
     * @param array<string,mixed> $soapOptions
     */
    public function __construct(
        array $soapOptions = []
    ) {
        if (!extension_loaded('soap')) {
            throw new TransportException("ext-soap is required for SoapXmlDriver.");
        }

        $wsdlUrl = 'https://webservices.nbs.rs/CommunicationOfficeService1_0/ExchangeRateXmlService.asmx?WSDL';

        $defaults = [
            'trace' => false,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_BOTH,
            'connection_timeout' => 15,
        ];

        $this->client = new \SoapClient($wsdlUrl, $soapOptions + $defaults);
    }

    public function getRatesXmlByDate(\DateTimeInterface $date, RateType $rateType): string
    {
        // NBS examples often represent dates as yyyyMMdd in SOAP params.
        $rateOnDate = $date->format('Ymd');
        $listType = $rateType->value;

        try {
            // Many NBS SOAP methods follow signature: (rateOnDate, listType, ...)
            // Exact method name depends on service: commonly "GetExchangeRateByDate".
            // If your WSDL uses a different name, adjust here.
            $res = $this->client->__soapCall('GetExchangeRateByDate', [
                [
                    'rateOnDate' => $rateOnDate,
                    'exchangeRateListTypeID' => $listType,
                ]
            ]);

            $xml = $this->extractXml($res);
            if ($xml === '') {
                throw new TransportException("Empty XML response from NBS SOAP service.");
            }
            return $xml;
        } catch (\SoapFault $e) {
            throw new TransportException("SOAP error: ".$e->getMessage(), previous: $e);
        }
    }

    public function getCurrentRatesXml(RateType $rateType): string
    {
        $listType = $rateType->value;

        try {
            $res = $this->client->__soapCall('GetCurrentExchangeRate', [
                [
                    'exchangeRateListTypeID' => $listType,
                ]
            ]);

            $xml = $this->extractXml($res);
            if ($xml === '') {
                throw new TransportException("Empty XML response from NBS SOAP service.");
            }
            return $xml;
        } catch (\SoapFault $e) {
            throw new TransportException("SOAP error: ".$e->getMessage(), previous: $e);
        }
    }

    private function extractXml(mixed $soapResponse): string
    {
        // SOAP responses may wrap result in different properties.
        // Try common patterns.
        if (is_string($soapResponse)) {
            return $soapResponse;
        }

        if (is_object($soapResponse)) {
            foreach (get_object_vars($soapResponse) as $v) {
                if (is_string($v) && str_contains($v, '<')) {
                    return $v;
                }
            }
        }

        return '';
    }
}

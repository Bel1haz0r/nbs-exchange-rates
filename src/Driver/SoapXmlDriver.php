<?php

namespace JustPhoenix\NbsExchangeRates\Driver;

use JustPhoenix\NbsExchangeRates\Contracts\Driver;
use JustPhoenix\NbsExchangeRates\Enum\RateType;
use JustPhoenix\NbsExchangeRates\Exception\TransportException;

final class SoapXmlDriver implements Driver
{
    private \SoapClient $client;

    private const NAMESPACE = 'http://communicationoffice.nbs.rs';

    /**
     * @param array<string,mixed> $soapOptions
     */
    public function __construct(
        array $soapOptions = []
    ) {
        if (!extension_loaded('soap')) {
            throw new TransportException("ext-soap is required for SoapXmlDriver.");
        }

        $wsdlUrl = $soapOptions['wsdl']
            ?? 'https://webservices.nbs.rs/CommunicationOfficeService1_0/ExchangeRateXmlService.asmx?WSDL';
        unset($soapOptions['wsdl']);

        // NBS authenticates via a SOAP header (AuthenticationHeader), not HTTP transport
        // auth, so username/password/licence must not be passed as plain SoapClient options.
        $username = $soapOptions['username'] ?? null;
        $password = $soapOptions['password'] ?? null;
        $licenceId = $soapOptions['licence_id'] ?? null;
        unset($soapOptions['username'], $soapOptions['password'], $soapOptions['licence_id']);

        $defaults = [
            'trace' => false,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_BOTH,
            'connection_timeout' => 15,
        ];

        $this->client = new \SoapClient($wsdlUrl, $soapOptions + $defaults);

        if ($username !== null || $password !== null || $licenceId !== null) {
            $this->client->__setSoapHeaders(new \SoapHeader(self::NAMESPACE, 'AuthenticationHeader', [
                'UserName' => $username,
                'Password' => $password,
                'LicenceID' => $licenceId,
            ]));
        }
    }

    public function getRatesXmlByDate(\DateTimeInterface $date, RateType $rateType): string
    {
        $listType = $rateType->value;

        try {
            // Despite every other date field in this service using dd.MM.yyyy display
            // format, GetExchangeRateByDate's `date` input parameter only accepts
            // unseparated yyyyMMdd — confirmed against the live service (any separator,
            // including ISO 8601, is rejected with an InputParametersError SOAP fault).
            $res = $this->client->__soapCall('GetExchangeRateByDate', [
                [
                    'date' => $date->format('Ymd'),
                    'exchangeRateListTypeID' => $listType,
                ]
            ]);

            $xml = $this->extractXml($res);
            if ($xml === '') {
                throw new TransportException("Empty XML response from NBS SOAP service.");
            }
            return $xml;
        } catch (\SoapFault $e) {
            throw new TransportException("SOAP error: ".$this->faultMessage($e), previous: $e);
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
            throw new TransportException("SOAP error: ".$this->faultMessage($e), previous: $e);
        }
    }

    private function faultMessage(\SoapFault $e): string
    {
        $detail = $e->detail->ErrorInfo->ErrorMessage ?? null;

        return $detail !== null ? "{$e->getMessage()} ({$detail})" : $e->getMessage();
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

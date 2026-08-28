<?php

namespace JustPhoenix\NbsExchangeRates\Parser;

use JustPhoenix\NbsExchangeRates\Exception\ParseException;
use JustPhoenix\NbsExchangeRates\Dto\ExchangeRate;
use JustPhoenix\NbsExchangeRates\Dto\ExchangeRateList;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class NbsXmlParser
{
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger()
    ) {}

    public function parseExchangeRates(string $xml): ExchangeRateList
    {
        libxml_clear_errors();
        $priorSetting = libxml_use_internal_errors(true);

        $sxml = simplexml_load_string($xml);
        if ($sxml === false) {
            $detail = $this->libxmlErrorSummary();
            libxml_use_internal_errors($priorSetting);
            $this->logger->error("Failed to parse NBS XML.", ['libxml' => $detail, 'xml' => $xml]);
            throw new ParseException("Failed to parse NBS XML." . ($detail !== '' ? " ({$detail})" : ''));
        }
        libxml_use_internal_errors($priorSetting);

        // The real ExchangeRateXmlService response shape is a flat, repeated element:
        // <ExchangeRateDataSet><ExchangeRate><Date>27.08.2026</Date>...
        //   <CurrencyCode>978</CurrencyCode> (numeric) <CurrencyCodeAlfaChar>EUR</CurrencyCodeAlfaChar> (alpha)
        //   ...<MiddleRate>...</MiddleRate></ExchangeRate>...</ExchangeRateDataSet>
        // Each item repeats the list-level Date/ListNumber/ListType, so we read them off the first item.
        // Some other NBS services may instead nest rows under a <header>/<item> pair — both are supported.
        $items = $sxml->ExchangeRate ?? $sxml->ExchRate ?? null;
        $header = null;

        if ($items === null) {
            $header = $sxml->header ?? null;
            $items = $sxml->item ?? $sxml->items?->item ?? $sxml->children();
        }

        $rates = [];
        $dateStr = '';
        $listNumber = '';
        $listType = '';

        foreach ($items as $node) {
            // Alpha code (e.g. "EUR") must take priority — CurrencyCode/Sifra are numeric ISO codes.
            $currencyCode = strtoupper((string)(
                $node->CurrencyCodeAlfaChar ?? $node->Currency ?? $node->oznaka ?? $node->Oznaka ?? ''
            ));
            if ($currencyCode === '') {
                continue;
            }

            if ($dateStr === '') {
                $dateStr = (string)($node->Date ?? $node->date ?? '');
            }
            if ($listNumber === '') {
                $listNumber = (string)($node->ExchangeRateListNumber ?? $node->No ?? $node->Number ?? '');
            }
            if ($listType === '') {
                $listType = (string)($node->ExchangeRateListTypeID ?? $node->Type ?? $node->ListType ?? '');
            }

            $numCode = (int)($node->CurrencyCode ?? $node->CurrencyCodeNumChar ?? $node->NumCode ?? $node->Sifra ?? $node->Code ?? 0);
            $unit    = (int)($node->Unit ?? $node->VaziZa ?? $node->ValidFor ?? 1);

            $country = (string)($node->CountryNameEng ?? $node->Country ?? $node->CountryName ?? $node->NazivZemlje ?? $node->Zemlja ?? '');

            // Rates may be formatted with comma decimal separators (Serbian locale).
            $middle  = $this->normDecimal((string)($node->MiddleRate ?? $node->SrednjiKurs ?? $node->Middle ?? ''));
            $buying  = $this->normDecimal((string)($node->BuyingRate ?? $node->KupovniKurs ?? $node->Buying ?? ''));
            $selling = $this->normDecimal((string)($node->SellingRate ?? $node->ProdajniKurs ?? $node->Selling ?? ''));

            $rates[] = new ExchangeRate(
                currencyCode: $currencyCode,
                currencyNumCode: $numCode,
                unit: $unit,
                countryName: $country !== '' ? $country : null,
                middle: $middle,
                buying: $buying,
                selling: $selling
            );
        }

        if (empty($rates)) {
            $this->logger->warning("Parsed NBS XML but found 0 rates.", ['xml' => $xml]);
            throw new ParseException("Parsed NBS XML but found 0 rates. XML shape may differ from expected format.");
        }

        if ($dateStr === '' && $header !== null) {
            $dateStr = (string)($header->Date ?? $header->date ?? '');
        }
        if ($listNumber === '' && $header !== null) {
            $listNumber = (string)($header->No ?? $header->Number ?? $header->ListNumber ?? '');
        }
        if ($listType === '' && $header !== null) {
            $listType = (string)($header->Type ?? $header->ListType ?? '');
        }

        $date = $this->parseDate($dateStr);

        return new ExchangeRateList(
            date: $date,
            rates: $rates,
            listNumber: $listNumber !== '' ? $listNumber : null,
            listType: $listType !== '' ? $listType : null
        );
    }

    private function parseDate(string $dateStr): \DateTimeImmutable
    {
        $dateStr = trim($dateStr);
        if ($dateStr === '') {
            // if missing, use "today" as a safe fallback
            return new \DateTimeImmutable('today');
        }

        // Try common formats: yyyy-mm-dd, dd.mm.yyyy, yyyyMMdd
        $formats = ['Y-m-d', 'd.m.Y', 'Ymd', \DateTimeInterface::ATOM];
        foreach ($formats as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat($fmt, $dateStr);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt;
            }
        }

        // last resort
        try {
            return new \DateTimeImmutable($dateStr);
        } catch (\Throwable) {
            return new \DateTimeImmutable('today');
        }
    }

    private function normDecimal(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        // NBS often uses Serbian-locale formatting: "." as thousands separator, "," as decimal
        // separator (e.g. "1.234,56"). Strip the thousands separator before swapping the decimal
        // comma, otherwise a value like "1.234,56" would become the corrupted "1.234.56".
        $value = str_replace([' ', "\xc2\xa0"], '', $value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        return $value;
    }

    private function libxmlErrorSummary(): string
    {
        $messages = array_map(
            static fn (\LibXMLError $e): string => trim($e->message) . " (line {$e->line})",
            libxml_get_errors()
        );
        libxml_clear_errors();
        return implode('; ', $messages);
    }
}

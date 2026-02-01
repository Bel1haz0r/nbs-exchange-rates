<?php

namespace JustPhoenix\NbsExchangeRates\Parser;

use JustPhoenix\NbsExchangeRates\Exception\ParseException;
use JustPhoenix\NbsExchangeRates\Dto\ExchangeRate;
use JustPhoenix\NbsExchangeRates\Dto\ExchangeRateList;

final class NbsXmlParser
{
    public function parseExchangeRates(string $xml): ExchangeRateList
    {
        libxml_use_internal_errors(true);

        $sxml = simplexml_load_string($xml);
        if ($sxml === false) {
            throw new ParseException("Failed to parse NBS XML.");
        }

        // NBS XML formats can vary depending on service.
        // This parser supports the common <Exchange_Rates_List> format seen in NBS downloads/services. :contentReference[oaicite:2]{index=2}
        // If your service returns a different shape, you can extend this parser or add another one.

        // Try: <Exchange_Rates_List><header>...<Date>...</Date>...</header><item>...</item>...
        $header = $sxml->header ?? null;

        $dateStr = (string)($header->Date ?? $header->date ?? '');
        if ($dateStr === '') {
            // fallback: try attribute or other node names if needed
            $dateStr = (string)($sxml->Date ?? '');
        }

        $date = $this->parseDate($dateStr);

        $listNumber = (string)($header->No ?? $header->Number ?? $header->ListNumber ?? '');
        $listType   = (string)($header->Type ?? $header->ListType ?? '');

        $rates = [];
        // Common pattern: <item> nodes (sometimes named <Exchange_Rate> or similar)
        $items = $sxml->item ?? $sxml->items?->item ?? null;

        if ($items === null) {
            // alternate: some XML may have <ExchangeRate> nodes
            $items = $sxml->ExchangeRate ?? $sxml->ExchRate ?? null;
        }

        if ($items === null) {
            // Last resort: scan children and pick those that look like rate rows
            $items = $sxml->children();
        }

        foreach ($items as $node) {
            $currencyCode = strtoupper((string)($node->Currency ?? $node->CurrencyCode ?? $node->oznaka ?? $node->Oznaka ?? ''));
            if ($currencyCode === '') {
                continue;
            }

            $numCode = (int)($node->CurrencyCodeNum ?? $node->NumCode ?? $node->Sifra ?? $node->Code ?? 0);
            $unit    = (int)($node->Unit ?? $node->VaziZa ?? $node->ValidFor ?? 1);

            $country = (string)($node->Country ?? $node->CountryName ?? $node->NazivZemlje ?? $node->Zemlja ?? '');

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
            throw new ParseException("Parsed NBS XML but found 0 rates. XML shape may differ from expected format.");
        }

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
        // NBS often uses comma as decimal separator in Serbian formatted outputs
        $value = str_replace([' ', "\xc2\xa0"], '', $value);
        $value = str_replace(',', '.', $value);
        return $value;
    }
}

<?php

namespace JustPhoenix\NbsExchangeRates\Tests\Parser;

use JustPhoenix\NbsExchangeRates\Exception\ParseException;
use JustPhoenix\NbsExchangeRates\Parser\NbsXmlParser;
use PHPUnit\Framework\TestCase;

final class NbsXmlParserTest extends TestCase
{
    private NbsXmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new NbsXmlParser();
    }

    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/' . $name);
    }

    public function testParsesFlatExchangeRateDataSetShape(): void
    {
        $list = $this->parser->parseExchangeRates($this->fixture('current_middle.xml'));

        self::assertSame('2026-08-27', $list->date->format('Y-m-d'));
        self::assertSame('166', $list->listNumber);
        self::assertCount(3, $list->rates);

        $eur = $list->find('EUR');
        self::assertNotNull($eur);
        self::assertSame(978, $eur->currencyNumCode);
        self::assertSame(1, $eur->unit);
        self::assertSame('117.2691', $eur->middle);
        self::assertSame('116.1005', $eur->buying);
        self::assertSame('118.4377', $eur->selling);
        self::assertSame('EMU', $eur->countryName);
    }

    public function testFindIsCaseInsensitive(): void
    {
        $list = $this->parser->parseExchangeRates($this->fixture('current_middle.xml'));

        self::assertNotNull($list->find('eur'));
        self::assertNull($list->find('XXX'));
    }

    public function testStripsThousandsSeparatorBeforeSwappingDecimalComma(): void
    {
        $list = $this->parser->parseExchangeRates($this->fixture('current_middle.xml'));

        $usd = $list->find('USD');
        self::assertNotNull($usd);
        // Serbian-formatted "1.234,56" must become "1234.56", not the corrupted "1.234.56".
        self::assertSame('1234.56', $usd->middle);
    }

    public function testParsesLegacyHeaderItemShape(): void
    {
        $list = $this->parser->parseExchangeRates($this->fixture('legacy_header_item.xml'));

        self::assertSame('2026-08-27', $list->date->format('Y-m-d'));
        self::assertSame('166', $list->listNumber);
        self::assertCount(1, $list->rates);

        $eur = $list->find('EUR');
        self::assertNotNull($eur);
        self::assertSame(978, $eur->currencyNumCode);
        self::assertSame('117.2691', $eur->middle);
    }

    public function testThrowsParseExceptionForMalformedXml(): void
    {
        $this->expectException(ParseException::class);
        $this->parser->parseExchangeRates('<ExchangeRateDataSet><ExchangeRate>');
    }

    public function testThrowsParseExceptionWhenNoRatesFound(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('found 0 rates');
        $this->parser->parseExchangeRates('<ExchangeRateDataSet></ExchangeRateDataSet>');
    }
}

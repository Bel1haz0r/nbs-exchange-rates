<?php

namespace JustPhoenix\NbsExchangeRates\Tests\Dto;

use JustPhoenix\NbsExchangeRates\Dto\ExchangeRate;
use JustPhoenix\NbsExchangeRates\Dto\ExchangeRateList;
use PHPUnit\Framework\TestCase;

final class ExchangeRateListTest extends TestCase
{
    public function testFindReturnsMatchingRate(): void
    {
        $eur = new ExchangeRate('EUR', 978, 1, 'EMU', '117.2691', '116.1005', '118.4377');
        $usd = new ExchangeRate('USD', 840, 1, 'USA', '107.50', '106.50', '108.50');

        $list = new ExchangeRateList(new \DateTimeImmutable('2026-08-27'), [$eur, $usd]);

        self::assertSame($eur, $list->find('EUR'));
        self::assertSame($usd, $list->find('usd'));
    }

    public function testFindReturnsNullWhenNotPresent(): void
    {
        $list = new ExchangeRateList(new \DateTimeImmutable('2026-08-27'), []);

        self::assertNull($list->find('EUR'));
    }
}

<?php

namespace JustPhoenix\NbsExchangeRates\Tests\Enum;

use JustPhoenix\NbsExchangeRates\Enum\RateType;
use PHPUnit\Framework\TestCase;

final class RateTypeTest extends TestCase
{
    public function testValuesMatchNbsExchangeRateListTypeId(): void
    {
        self::assertSame(1, RateType::BUYING_SELLING->value);
        self::assertSame(2, RateType::EFFECTIVE->value);
        self::assertSame(3, RateType::MIDDLE->value);
    }
}

<?php

namespace JustPhoenix\NbsExchangeRates\Tests;

use JustPhoenix\NbsExchangeRates\Contracts\Driver;
use JustPhoenix\NbsExchangeRates\Enum\RateType;
use JustPhoenix\NbsExchangeRates\NbsClient;
use PHPUnit\Framework\TestCase;

final class NbsClientTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/Fixtures/' . $name);
    }

    public function testGetCurrentDelegatesToDriverAndParsesXml(): void
    {
        $xml = $this->fixture('current_middle.xml');

        $driver = new class($xml) implements Driver {
            public ?RateType $requestedRateType = null;

            public function __construct(private readonly string $xml) {}

            public function getRatesXmlByDate(\DateTimeInterface $date, RateType $rateType): string
            {
                throw new \LogicException('not expected');
            }

            public function getCurrentRatesXml(RateType $rateType): string
            {
                $this->requestedRateType = $rateType;
                return $this->xml;
            }
        };

        $client = new NbsClient($driver);
        $list = $client->getCurrent(RateType::MIDDLE);

        self::assertSame(RateType::MIDDLE, $driver->requestedRateType);
        self::assertNotNull($list->find('EUR'));
    }

    public function testGetByDateDelegatesToDriverAndParsesXml(): void
    {
        $xml = $this->fixture('current_middle.xml');

        $driver = new class($xml) implements Driver {
            public ?\DateTimeInterface $requestedDate = null;

            public function __construct(private readonly string $xml) {}

            public function getRatesXmlByDate(\DateTimeInterface $date, RateType $rateType): string
            {
                $this->requestedDate = $date;
                return $this->xml;
            }

            public function getCurrentRatesXml(RateType $rateType): string
            {
                throw new \LogicException('not expected');
            }
        };

        $client = new NbsClient($driver);
        $date = new \DateTimeImmutable('2026-08-27');
        $list = $client->getByDate($date, RateType::MIDDLE);

        self::assertSame($date, $driver->requestedDate);
        self::assertNotNull($list->find('USD'));
    }
}

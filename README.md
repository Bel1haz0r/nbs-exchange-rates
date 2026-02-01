# NBS Exchange Rates

A PHP client for fetching exchange rates from the
National Bank of Serbia (NBS) SOAP XML services.

This package:
- Uses official NBS SOAP services
- Parses XML into typed DTOs
---

## Requirements

- PHP 8.1+
- ext-soap (required for SoapXmlDriver)
- ext-libxml
- ext-simplexml
---

## Installation
Add private repository to composer
```bash
composer config --global repositories.justphoenix composer https://packages.justphoenix.io
```

Install the package
```bash
composer require justphoenix/nbs-exchange-rates
```

## Basic usage

```php
use YourVendor\NbsExchangeRates\Driver\SoapXmlDriver;
use YourVendor\NbsExchangeRates\NbsClient;
use YourVendor\NbsExchangeRates\Enum\RateType;

$options = [
        'login' => 'NBS_USER',
        'password' => 'NBS_PASS',
        'trace' => true,
        'exceptions' => true,
        // optional:
        // 'authentication' => SOAP_AUTHENTICATION_BASIC,
        // 'stream_context' => stream_context_create([...]),
    ];

$client = new NbsClient(new SoapXmlDriver($options));

$list = $client->getCurrent(RateType::MIDDLE);

echo $list->date->format('Y-m-d');

$eur = $list->find('EUR');
echo $eur?->middle;
```

## Fetch Rates By Date
```php
$date = new DateTimeImmutable('2026-01-01');
$list = $client->getByDate($date, RateType::MIDDLE);
```

## Rate Types
```php
RateType::MIDDLE
RateType::EFFECTIVE
RateType::BUYING_SELLING
```
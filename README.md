# NBS Exchange Rates

A framework-agnostic PHP client for fetching official exchange rates from the
National Bank of Serbia (NBS) `ExchangeRateXmlService` SOAP web service.

- Talks to the official NBS SOAP service (`ext-soap`)
- Parses the XML response into typed, immutable DTOs
- No framework dependencies — works in Laravel, Symfony, or plain PHP
- Optional PSR-3 logging of transport and parsing failures

---

## Requirements

- PHP 8.1+
- `ext-soap` (required by the bundled `SoapXmlDriver`)
- `ext-libxml`
- `ext-simplexml`

## Installation

Add the private repository to Composer:

```bash
composer config --global repositories.justphoenix composer https://packages.justphoenix.io
```

Install the package:

```bash
composer require justphoenix/nbs-exchange-rates
```

---

## Quick start

```php
use JustPhoenix\NbsExchangeRates\Driver\SoapXmlDriver;
use JustPhoenix\NbsExchangeRates\NbsClient;
use JustPhoenix\NbsExchangeRates\Enum\RateType;

$driver = new SoapXmlDriver([
    'username' => 'NBS_USER',
    'password' => 'NBS_PASS',
]);

$client = new NbsClient($driver);

$list = $client->getCurrent(RateType::MIDDLE);

echo $list->date->format('Y-m-d');   // e.g. "2026-08-27"

$eur = $list->find('EUR');
echo $eur?->middle;                  // e.g. "117.2691"
```

Currency lookups via `find()` are case-insensitive (`'eur'` and `'EUR'` both work), and
return `null` if the currency isn't in the list — always guard with `?->` or a null check.

---

## Fetching rates by date

```php
$date = new DateTimeImmutable('2026-01-01');
$list = $client->getByDate($date, RateType::MIDDLE);

foreach ($list->rates as $rate) {
    printf("%s: %s\n", $rate->currencyCode, $rate->middle);
}
```

`getByDate()` accepts any `DateTimeInterface` (`DateTime` or `DateTimeImmutable`). Only the
calendar date is used — NBS does not return intraday rates.

---

## Rate types

NBS publishes three parallel lists, selected via `RateType`:

```php
RateType::MIDDLE;         // srednji kurs — the reference/mid rate (default)
RateType::BUYING_SELLING; // devizni kurs — commercial buying/selling rates
RateType::EFFECTIVE;      // efektivni kurs — cash exchange rates
```

Pass one to either client method:

```php
$list = $client->getCurrent(RateType::EFFECTIVE);
```

If omitted, both `getCurrent()` and `getByDate()` default to `RateType::MIDDLE`.

---

## Working with the result

### `ExchangeRateList`

| Property     | Type               | Description                                  |
|--------------|--------------------|-----------------------------------------------|
| `date`       | `DateTimeImmutable`| The date the list is published for            |
| `rates`      | `ExchangeRate[]`   | All currencies in the list                     |
| `listNumber` | `?string`          | NBS's list/sequence number, if present         |
| `listType`   | `?string`          | Raw list type identifier, if present           |

```php
$eur = $list->find('EUR'); // ?ExchangeRate, case-insensitive lookup
```

### `ExchangeRate`

| Property          | Type      | Description                                      |
|-------------------|-----------|---------------------------------------------------|
| `currencyCode`    | `string`  | ISO alpha code, e.g. `"EUR"`                       |
| `currencyNumCode` | `int`     | ISO numeric code, e.g. `978`                       |
| `unit`            | `int`     | Quotation unit, e.g. `1` or `100` (JPY, etc.)      |
| `countryName`     | `?string` | Country/issuer name, if provided by NBS            |
| `middle`          | `?string` | Middle rate, as a decimal string                   |
| `buying`          | `?string` | Buying rate, as a decimal string                   |
| `selling`         | `?string` | Selling rate, as a decimal string                  |

Rates are returned as decimal **strings**, not floats, so you can pass them straight into an
arbitrary-precision library (e.g. `bcmath`, `brick/math`) without floating-point rounding error.
Decimal separators are always normalized to `.` regardless of NBS's Serbian-locale formatting
(`","` decimal, `"."` thousands).

---

## SOAP driver options

`SoapXmlDriver` wraps PHP's native `SoapClient` and accepts an options array plus an optional
PSR-3 logger:

```php
use Psr\Log\LoggerInterface;

new SoapXmlDriver(array $soapOptions = [], ?LoggerInterface $logger = null);
```

| Option        | Description                                                                 |
|---------------|-------------------------------------------------------------------------------|
| `username`    | NBS account username, sent via the SOAP `AuthenticationHeader` (not HTTP auth) |
| `password`    | NBS account password, sent the same way                                        |
| `licence_id`  | Optional NBS licence identifier                                                |
| `wsdl`        | Override the WSDL URL (defaults to the official NBS endpoint)                  |
| *(anything else)* | Passed straight through to PHP's `SoapClient` (`trace`, `connection_timeout`, `stream_context`, ...) |

```php
$driver = new SoapXmlDriver([
    'username' => 'NBS_USER',
    'password' => 'NBS_PASS',
    'trace' => true,
    'connection_timeout' => 15,
    // 'stream_context' => stream_context_create([...]),
]);
```

> **Note:** NBS authenticates via a SOAP header, not HTTP Basic Auth — use `username`/`password`
> above, not SoapClient's native `login`/`password` options.

---

## Logging

Both `SoapXmlDriver` and the internal `NbsXmlParser` accept an optional
[PSR-3](https://www.php-fig.org/psr/psr-3/) `LoggerInterface`. When provided, SOAP faults and
XML parsing failures are logged (with the raw response/XML as context) before the corresponding
exception is thrown — useful for diagnosing malformed or unexpected NBS responses in production.

```php
use JustPhoenix\NbsExchangeRates\Parser\NbsXmlParser;

$driver = new SoapXmlDriver($options, logger: $myLogger);
$client = new NbsClient($driver, new NbsXmlParser($myLogger));
```

Logging is entirely optional — if no logger is given, a no-op `Psr\Log\NullLogger` is used.

---

## Error handling

All exceptions extend `JustPhoenix\NbsExchangeRates\Exception\NbsException`:

| Exception            | Thrown when...                                                        |
|-----------------------|-------------------------------------------------------------------------|
| `TransportException` | The SOAP call fails, times out, or returns an empty/unusable response  |
| `ParseException`      | The XML response can't be parsed, or contains no recognizable rates    |

```php
use JustPhoenix\NbsExchangeRates\Exception\NbsException;
use JustPhoenix\NbsExchangeRates\Exception\TransportException;
use JustPhoenix\NbsExchangeRates\Exception\ParseException;

try {
    $list = $client->getCurrent();
} catch (TransportException $e) {
    // network / SOAP fault — safe to retry
} catch (ParseException $e) {
    // unexpected response shape from NBS
} catch (NbsException $e) {
    // catch-all for the two above
}
```

---

## Using a custom driver

The client only depends on the `Driver` contract, so you can swap `SoapXmlDriver` for anything
that returns raw NBS XML — a REST proxy, a cached fixture, a different transport:

```php
use JustPhoenix\NbsExchangeRates\Contracts\Driver;
use JustPhoenix\NbsExchangeRates\Enum\RateType;

final class CachedFileDriver implements Driver
{
    public function getCurrentRatesXml(RateType $rateType): string
    {
        return file_get_contents(__DIR__ . "/cache/current-{$rateType->value}.xml");
    }

    public function getRatesXmlByDate(\DateTimeInterface $date, RateType $rateType): string
    {
        return file_get_contents(__DIR__ . "/cache/{$date->format('Ymd')}-{$rateType->value}.xml");
    }
}

$client = new NbsClient(new CachedFileDriver());
```

You can also call the parser directly if you already have raw XML from another source:

```php
use JustPhoenix\NbsExchangeRates\Parser\NbsXmlParser;

$list = (new NbsXmlParser())->parseExchangeRates($xml);
```

---

## Testing

```bash
composer install
composer test
```

This runs the PHPUnit suite in `tests/`, covering the parser (against fixture XML for both
response shapes NBS is known to return), the DTOs, `RateType`, and `NbsClient` (via a fake
`Driver`) — no network access or NBS credentials required.

---

## License

MIT

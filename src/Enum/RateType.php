<?php

namespace JustPhoenix\NbsExchangeRates\Enum;

enum RateType: int
{
    // NBS uses list types (e.g. devize/effective/middle). Exact mapping can differ by endpoint.
    // Keep this generic; driver maps these to NBS params.
    case MIDDLE = 3;
    case BUYING_SELLING = 1; // example placeholder
    case EFFECTIVE = 2;      // example placeholder
}

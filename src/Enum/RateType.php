<?php

namespace JustPhoenix\NbsExchangeRates\Enum;

enum RateType: int
{
    // Maps directly to NBS's exchangeRateListTypeID parameter:
    // 1 = devize (foreign currency buying/selling), 2 = efektiva (effective/cash), 3 = srednji kurs (middle).
    case BUYING_SELLING = 1;
    case EFFECTIVE = 2;
    case MIDDLE = 3;
}

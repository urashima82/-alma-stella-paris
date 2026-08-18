<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The four axes of the audience aggregate. One generic table holds a `views`
 * counter per `(day, dimension, value)`; a wide row per day would be a
 * cartesian product and would stop aggregating.
 *
 * Daily totals are a `SUM(views)` over {@see self::Page} — there is deliberately
 * no total dimension to keep in sync.
 */
enum StatDimension: string
{
    case Page = 'page';
    case Referrer = 'referrer';
    case Locale = 'locale';
    case Device = 'device';
}

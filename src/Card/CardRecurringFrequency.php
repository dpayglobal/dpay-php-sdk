<?php

declare(strict_types=1);

namespace DPay\Card;

use InvalidArgumentException;

final class CardRecurringFrequency
{
    public const DAILY = 'DAILY';
    public const WEEKLY = 'WEEKLY';
    public const BIWEEKLY = 'BIWEEKLY';
    public const MONTHLY = 'MONTHLY';
    public const QUARTERLY = 'QUARTERLY';
    public const SEMIANNUAL = 'SEMIANNUAL';
    public const ANNUAL = 'ANNUAL';

    public const ALL = [
        self::DAILY,
        self::WEEKLY,
        self::BIWEEKLY,
        self::MONTHLY,
        self::QUARTERLY,
        self::SEMIANNUAL,
        self::ANNUAL,
    ];

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Invalid card recurring frequency "%s"', $value));
        }
    }

    private function __construct()
    {
    }
}

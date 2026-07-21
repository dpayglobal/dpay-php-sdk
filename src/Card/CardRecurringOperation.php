<?php

declare(strict_types=1);

namespace DPay\Card;

use InvalidArgumentException;

final class CardRecurringOperation
{
    public const ADD_CARD = 'add_card';
    public const COF_INITIAL = 'cof_initial';
    public const CHARGE = 'charge';

    public const ALL = [
        self::ADD_CARD,
        self::COF_INITIAL,
        self::CHARGE,
    ];

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Invalid card recurring operation "%s"', $value));
        }
    }

    private function __construct()
    {
    }
}

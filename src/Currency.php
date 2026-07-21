<?php

declare(strict_types=1);

namespace DPay;

use InvalidArgumentException;

final class Currency
{
    public const PLN = 'PLN';
    public const EUR = 'EUR';
    public const CZK = 'CZK';

    public static function isValid(string $currency): bool
    {
        return preg_match('/^[A-Z]{3}$/', $currency) === 1;
    }

    public static function assertValid(string $currency): void
    {
        if (!self::isValid($currency)) {
            throw new InvalidArgumentException(sprintf('Invalid currency code "%s"', $currency));
        }
    }

    private function __construct()
    {
    }
}

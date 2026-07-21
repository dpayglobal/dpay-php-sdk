<?php

declare(strict_types=1);

namespace DPay\Payment;

use InvalidArgumentException;

final class PayoutFeeMode
{
    public const NET = 'net';
    public const GROSS = 'gross';

    public const ALL = [
        self::NET,
        self::GROSS,
    ];

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Invalid payout fee mode "%s"', $value));
        }
    }

    private function __construct()
    {
    }
}

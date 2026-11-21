<?php

declare(strict_types=1);

namespace DPay\Payout;

use InvalidArgumentException;

/** Payout `type` in the payout list: `direct` covers exactly one payment (1:1 payout instruction, refund form payout). */
final class PayoutType
{
    public const STANDARD = 'standard';
    public const DIRECT = 'direct';
    public const PAYSAFECARD = 'paysafecard';

    public const ALL = [
        self::STANDARD,
        self::DIRECT,
        self::PAYSAFECARD,
    ];

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Invalid payout type "%s"', $value));
        }
    }

    private function __construct()
    {
    }
}

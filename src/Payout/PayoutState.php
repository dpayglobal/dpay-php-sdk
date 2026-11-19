<?php

declare(strict_types=1);

namespace DPay\Payout;

use InvalidArgumentException;

/** Payout `state`: 0 waiting, 1 processed, -1 failure. */
final class PayoutState
{
    public const WAITING = 0;
    public const PROCESSED = 1;
    public const FAILED = -1;

    public const ALL = [
        self::WAITING,
        self::PROCESSED,
        self::FAILED,
    ];

    public static function assertValid(int $value): void
    {
        if (!in_array($value, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Invalid payout state "%d"', $value));
        }
    }

    private function __construct()
    {
    }
}

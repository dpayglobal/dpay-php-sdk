<?php

declare(strict_types=1);

namespace DPay\Card;

use InvalidArgumentException;

final class DccDecision
{
    public const ACCEPT = 'accept';
    public const REJECT = 'reject';

    public const ALL = [
        self::ACCEPT,
        self::REJECT,
    ];

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Invalid DCC decision "%s"', $value));
        }
    }

    private function __construct()
    {
    }
}

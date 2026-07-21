<?php

declare(strict_types=1);

namespace DPay\Blik;

use InvalidArgumentException;

final class BlikAliasType
{
    public const UID = 'UID';
    public const PAYID = 'PAYID';

    public const ALL = [
        self::UID,
        self::PAYID,
    ];

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Invalid BLIK alias type "%s"', $value));
        }
    }

    private function __construct()
    {
    }
}

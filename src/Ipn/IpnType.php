<?php

declare(strict_types=1);

namespace DPay\Ipn;

use InvalidArgumentException;

final class IpnType
{
    public const TRANSFER = 'transfer';
    public const CAPTURE = 'capture';
    public const DCB = 'dcb';

    public const ALL = [
        self::TRANSFER,
        self::CAPTURE,
        self::DCB,
    ];

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Invalid IPN type "%s"', $value));
        }
    }

    private function __construct()
    {
    }
}

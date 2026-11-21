<?php

declare(strict_types=1);

namespace DPay\Ipn;

use InvalidArgumentException;

final class IpnType
{
    public const TRANSFER = 'transfer';
    /** @deprecated dpay no longer sends capture IPNs - use the `payment.captured` webhook event. */
    public const CAPTURE = 'capture';
    public const DCB = 'dcb';
    /** BLIK OneClick alias or recurring payment status change, sent to `alias_ipn_url` (or `url_ipn`). */
    public const ALIAS_UPDATE = 'alias_update';

    public const ALL = [
        self::TRANSFER,
        self::CAPTURE,
        self::DCB,
        self::ALIAS_UPDATE,
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

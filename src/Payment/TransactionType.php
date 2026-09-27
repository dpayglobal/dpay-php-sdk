<?php

declare(strict_types=1);

namespace DPay\Payment;

use InvalidArgumentException;

final class TransactionType
{
    public const TRANSFERS = 'transfers';
    public const DCB_GATEWAY = 'dcb_gateway';
    public const CARD_AUTH = 'card_auth';
    public const MB_WAY_DIRECT = 'mb_way_direct';
    public const CARD_RECURRING = 'card_recurring';

    public const ALL = [
        self::TRANSFERS,
        self::DCB_GATEWAY,
        self::CARD_AUTH,
        self::MB_WAY_DIRECT,
        self::CARD_RECURRING,
    ];

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Invalid transaction type "%s"', $value));
        }
    }

    private function __construct()
    {
    }
}

<?php

declare(strict_types=1);

namespace DPay\Payment;

use InvalidArgumentException;

final class TransactionStatus
{
    public const PAID = 'paid';
    public const CREATED = 'created';
    public const PROCESSING = 'processing';
    public const EXPIRED = 'expired';
    public const CAPTURED = 'captured';

    public const ALL = [
        self::PAID,
        self::CREATED,
        self::PROCESSING,
        self::EXPIRED,
        self::CAPTURED,
    ];

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Invalid transaction status "%s"', $value));
        }
    }

    private function __construct()
    {
    }
}

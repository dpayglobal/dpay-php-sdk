<?php

declare(strict_types=1);

namespace DPay\Card;

use InvalidArgumentException;

final class RedirectType
{
    public const SUCCESS = 'SUCCESS';
    public const FORM = 'FORM';
    public const URL = 'URL';
    public const DCC_OFFER = 'DCC_OFFER';

    public const ALL = [
        self::SUCCESS,
        self::FORM,
        self::URL,
        self::DCC_OFFER,
    ];

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Invalid redirect type "%s"', $value));
        }
    }

    private function __construct()
    {
    }
}

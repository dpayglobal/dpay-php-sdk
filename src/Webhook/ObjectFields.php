<?php

declare(strict_types=1);

namespace DPay\Webhook;

use DPay\Currency;
use DPay\Money;

/**
 * @internal readers shared by the webhook object models
 */
final class ObjectFields
{
    /**
     * @param array<mixed> $data
     */
    public static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) || is_int($value) ? (string) $value : null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function intOrNull(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>|null
     */
    public static function arrayOrNull(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * `currency` of the object, PLN when missing or invalid.
     *
     * @param array<mixed> $data
     */
    public static function currency(array $data): string
    {
        $currency = $data['currency'] ?? null;

        return is_string($currency) && Currency::isValid($currency) ? $currency : Currency::PLN;
    }

    /**
     * Amount in minor units (an integer in the payload); 0 when missing.
     *
     * @param array<mixed> $data
     */
    public static function money(array $data, string $key, string $currency): Money
    {
        $minor = $data[$key] ?? null;

        return Money::of(is_int($minor) ? $minor : 0, $currency);
    }

    private function __construct()
    {
    }
}

<?php

declare(strict_types=1);

namespace DPay\Payment;

use DPay\Currency;
use DPay\Money;

/**
 * Multibanco payment reference returned by the registration (`multibanco`): the payer pays it at an ATM or in online
 * banking with the entity and reference numbers, so you can show them without redirecting to the gateway.
 */
final class MultibancoReference
{
    /** @var array<mixed> */
    private array $raw;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw)
    {
        $this->raw = $raw;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /** Entity number (5 digits). */
    public function getEntity(): string
    {
        return $this->string('entity') ?? '';
    }

    /** Reference number (9 digits, without spaces). */
    public function getReference(): string
    {
        return $this->string('reference') ?? '';
    }

    /** Amount to pay in getCurrency() (EUR). */
    public function getAmount(): ?Money
    {
        return Money::tryFromApiNumber($this->raw['amount'] ?? null, $this->getCurrency());
    }

    public function getCurrency(): string
    {
        $currency = $this->string('currency');

        return $currency !== null && Currency::isValid($currency) ? $currency : Currency::EUR;
    }

    /** Expiry in UTC, ISO 8601 (e.g. `2026-11-21T10:00:00+00:00`). */
    public function getExpiresAt(): ?string
    {
        return $this->string('expires_at');
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    private function string(string $key): ?string
    {
        $value = $this->raw[$key] ?? null;

        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}

<?php

declare(strict_types=1);

namespace DPay\Payout;

use DPay\Money;

/** A payment settled by a payout (`transactions[]` of `withdraws/details/full`). */
final class SettlementPosition
{
    /** @var array<mixed> */
    private array $raw;

    private string $currency;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw, string $currency)
    {
        $this->raw = $raw;
        $this->currency = $currency;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data, string $currency): self
    {
        return new self($data, $currency);
    }

    /** Payment ID - the same as in the IPN; null when unknown. */
    public function getPaymentId(): ?string
    {
        return $this->string('payment_id');
    }

    /** When the payment was made. */
    public function getDate(): ?string
    {
        return $this->string('date');
    }

    /** Amount credited to you. */
    public function getNet(): Money
    {
        return $this->money('net');
    }

    /** Commission charged on this payment. */
    public function getFee(): Money
    {
        return $this->money('fee');
    }

    /** Amount paid by the payer. */
    public function getGross(): Money
    {
        return $this->money('gross');
    }

    public function getServiceId(): ?int
    {
        $serviceId = $this->raw['service_id'] ?? null;

        return is_int($serviceId) ? $serviceId : null;
    }

    public function getServiceName(): ?string
    {
        return $this->string('service_name');
    }

    /** The `custom` value from the registration, as in the IPN. */
    public function getCustom(): ?string
    {
        return $this->string('custom');
    }

    /** KSeF payment identifier of a payment settled with an e-invoice. */
    public function getIpksef(): ?string
    {
        return $this->string('ipksef');
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    private function money(string $key): Money
    {
        return Money::tryFromApiNumber($this->raw[$key] ?? 0, $this->currency) ?? Money::of(0, $this->currency);
    }

    private function string(string $key): ?string
    {
        $value = $this->raw[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}

<?php

declare(strict_types=1);

namespace DPay\Payout;

use DPay\Currency;
use DPay\Money;

final class PayoutReceiver
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
    public static function fromArray(array $data, string $currency = Currency::PLN): self
    {
        return new self($data, Currency::isValid($currency) ? $currency : Currency::PLN);
    }

    public function getNrb(): ?string
    {
        return isset($this->raw['nrb']) && is_string($this->raw['nrb']) ? $this->raw['nrb'] : null;
    }

    public function getTitle(): ?string
    {
        return isset($this->raw['title']) && is_string($this->raw['title']) ? $this->raw['title'] : null;
    }

    public function getAmount(): ?Money
    {
        return Money::tryFromApiNumber($this->raw['amount'] ?? null, $this->currency);
    }

    public function getService(): ?string
    {
        return isset($this->raw['service']) && is_string($this->raw['service']) ? $this->raw['service'] : null;
    }

    public function getReceiverName(): ?string
    {
        return isset($this->raw['receiverName']) && is_string($this->raw['receiverName']) ? $this->raw['receiverName'] : null;
    }

    public function getReceiverAddress(): ?string
    {
        return isset($this->raw['receiverAddress']) && is_string($this->raw['receiverAddress']) ? $this->raw['receiverAddress'] : null;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

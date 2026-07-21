<?php

declare(strict_types=1);

namespace DPay\Blik;

final class BlikRecurringRegistrationInfo
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

    public function getModel(): ?string
    {
        return isset($this->raw['model']) && is_string($this->raw['model']) ? $this->raw['model'] : null;
    }

    public function getFrequency(): ?string
    {
        return isset($this->raw['frequency']) && is_string($this->raw['frequency']) ? $this->raw['frequency'] : null;
    }

    public function getLimitAmt(): ?int
    {
        return isset($this->raw['limit_amt']) && is_int($this->raw['limit_amt']) ? $this->raw['limit_amt'] : null;
    }

    public function getTotLimitAmt(): ?int
    {
        return isset($this->raw['tot_limit_amt']) && is_int($this->raw['tot_limit_amt'])
            ? $this->raw['tot_limit_amt']
            : null;
    }

    public function isLimitAmtFixed(): ?bool
    {
        return isset($this->raw['is_limit_amt_fixed']) && is_bool($this->raw['is_limit_amt_fixed'])
            ? $this->raw['is_limit_amt_fixed']
            : null;
    }

    public function getInitDate(): ?string
    {
        return isset($this->raw['init_date']) && is_string($this->raw['init_date']) ? $this->raw['init_date'] : null;
    }

    public function getLabel(): ?string
    {
        return isset($this->raw['label']) && is_string($this->raw['label']) ? $this->raw['label'] : null;
    }

    public function getRegisteredAt(): ?string
    {
        return isset($this->raw['registered_at']) && is_string($this->raw['registered_at'])
            ? $this->raw['registered_at']
            : null;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

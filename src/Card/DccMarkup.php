<?php

declare(strict_types=1);

namespace DPay\Card;

final class DccMarkup
{
    private float $rate;

    private ?string $additionalInfo;

    private function __construct(float $rate, ?string $additionalInfo)
    {
        $this->rate = $rate;
        $this->additionalInfo = $additionalInfo;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rate = $data['rate'] ?? 0;

        return new self(
            is_numeric($rate) ? (float) $rate : 0.0,
            isset($data['additionalInfo']) && is_string($data['additionalInfo']) ? $data['additionalInfo'] : null
        );
    }

    public function getRate(): float
    {
        return $this->rate;
    }

    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }
}

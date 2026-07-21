<?php

declare(strict_types=1);

namespace DPay\Blik;

final class BlikApp
{
    private ?string $key;

    private ?string $label;

    private function __construct(?string $key, ?string $label)
    {
        $this->key = $key;
        $this->label = $label;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['key']) && is_string($data['key']) ? $data['key'] : null,
            isset($data['label']) && is_string($data['label']) ? $data['label'] : null
        );
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }
}

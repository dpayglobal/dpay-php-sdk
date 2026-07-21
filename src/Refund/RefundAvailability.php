<?php

declare(strict_types=1);

namespace DPay\Refund;

final class RefundAvailability
{
    /** @var array<mixed> */
    private array $raw;

    private bool $available;

    private ?string $message;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw, bool $available, ?string $message)
    {
        $this->raw = $raw;
        $this->available = $available;
        $this->message = $message;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            ($data['refund'] ?? false) === true,
            isset($data['message']) && is_scalar($data['message']) ? (string) $data['message'] : null
        );
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

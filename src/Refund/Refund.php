<?php

declare(strict_types=1);

namespace DPay\Refund;

final class Refund
{
    /** @var array<mixed> */
    private array $raw;

    private bool $accepted;

    private ?string $message;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw, bool $accepted, ?string $message)
    {
        $this->raw = $raw;
        $this->accepted = $accepted;
        $this->message = $message;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            ($data['status'] ?? null) === 'success' && ($data['refund'] ?? false) === true,
            isset($data['message']) && is_scalar($data['message']) ? (string) $data['message'] : null
        );
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
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

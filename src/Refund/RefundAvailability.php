<?php

declare(strict_types=1);

namespace DPay\Refund;

final class RefundAvailability
{
    /** @var array<mixed> */
    private array $raw;

    private bool $available;

    private ?string $message;

    private ?int $httpStatus;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw, bool $available, ?string $message, ?int $httpStatus)
    {
        $this->raw = $raw;
        $this->available = $available;
        $this->message = $message;
        $this->httpStatus = $httpStatus;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data, ?int $httpStatus = null): self
    {
        return new self(
            $data,
            ($data['refund'] ?? false) === true,
            isset($data['message']) && is_scalar($data['message']) ? (string) $data['message'] : null,
            $httpStatus
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

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

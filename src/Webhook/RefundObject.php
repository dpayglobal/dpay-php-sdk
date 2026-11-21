<?php

declare(strict_types=1);

namespace DPay\Webhook;

use DPay\Money;

/** The `refund` object of refund.* events. */
final class RefundObject
{
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

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

    /** Refund ID (`ZWROT-...`); null for a refund rejected before it was created. */
    public function getId(): ?string
    {
        return ObjectFields::string($this->raw, 'id');
    }

    /** ID of the refunded payment. */
    public function getPayment(): ?string
    {
        return ObjectFields::string($this->raw, 'payment');
    }

    public function getStatus(): ?string
    {
        return ObjectFields::string($this->raw, 'status');
    }

    public function getCurrency(): string
    {
        return ObjectFields::currency($this->raw);
    }

    public function getAmount(): Money
    {
        return ObjectFields::money($this->raw, 'amount', $this->getCurrency());
    }

    public function getCreated(): ?string
    {
        return ObjectFields::string($this->raw, 'created');
    }

    public function getSucceededAt(): ?string
    {
        return ObjectFields::string($this->raw, 'succeeded_at');
    }

    /** Rejection reason in refund.failed, e.g. `refund_window_expired`. */
    public function getFailure(): ?Failure
    {
        $failure = ObjectFields::arrayOrNull($this->raw, 'failure');

        return $failure === null ? null : Failure::fromArray($failure);
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

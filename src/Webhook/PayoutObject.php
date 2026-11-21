<?php

declare(strict_types=1);

namespace DPay\Webhook;

use DPay\Money;

/** The `payout` object of payout.* events (account events, without a service). */
final class PayoutObject
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
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

    /** Payout ID - the `withdraw_id` of payouts->details(). */
    public function getId(): string
    {
        return ObjectFields::string($this->raw, 'id') ?? '';
    }

    public function getStatus(): ?string
    {
        return ObjectFields::string($this->raw, 'status');
    }

    public function getCurrency(): string
    {
        return ObjectFields::currency($this->raw);
    }

    /** Net payout amount. */
    public function getAmount(): Money
    {
        return ObjectFields::money($this->raw, 'amount', $this->getCurrency());
    }

    public function getFee(): Money
    {
        return ObjectFields::money($this->raw, 'fee', $this->getCurrency());
    }

    /** Last 4 digits of the destination account. */
    public function getBankAccountLast4(): ?string
    {
        return ObjectFields::string(ObjectFields::arrayOrNull($this->raw, 'bank_account') ?? [], 'last4');
    }

    public function getCreated(): ?string
    {
        return ObjectFields::string($this->raw, 'created');
    }

    public function getPaidAt(): ?string
    {
        return ObjectFields::string($this->raw, 'paid_at');
    }

    /** In payout.failed: `payout_failed`, getProviderCode() tells what happened to the funds. */
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

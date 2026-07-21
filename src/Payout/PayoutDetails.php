<?php

declare(strict_types=1);

namespace DPay\Payout;

use DPay\Currency;
use DPay\Money;

final class PayoutDetails
{
    /** @var array<mixed> */
    private array $raw;

    private int $id;

    private int $state;

    private Money $net;

    private Money $fee;

    private Money $gross;

    private ?string $creationDate;

    private bool $directSettlement;

    private ?string $nrb;

    private bool $declined;

    private ?string $declineReason;

    private ?string $declineStatus;

    private ?PayoutReceiver $receiver;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw)
    {
        $this->raw = $raw;
        $this->id = isset($raw['id']) && is_scalar($raw['id']) ? (int) $raw['id'] : 0;
        $this->state = isset($raw['state']) && is_scalar($raw['state']) ? (int) $raw['state'] : 0;
        $this->net = Money::tryFromApiNumber($raw['net'] ?? 0, Currency::PLN) ?? Money::pln(0);
        $this->fee = Money::tryFromApiNumber($raw['fee'] ?? 0, Currency::PLN) ?? Money::pln(0);
        $this->gross = Money::tryFromApiNumber($raw['gross'] ?? 0, Currency::PLN) ?? Money::pln(0);
        $this->creationDate = isset($raw['creation_date']) && is_string($raw['creation_date'])
            ? $raw['creation_date']
            : null;
        $this->directSettlement = isset($raw['direct_settlement']) && is_scalar($raw['direct_settlement'])
            ? (int) $raw['direct_settlement'] === 1
            : false;
        $this->nrb = isset($raw['nrb']) && is_string($raw['nrb']) ? $raw['nrb'] : null;
        $this->declined = isset($raw['declined']) && is_scalar($raw['declined'])
            ? (int) $raw['declined'] === 1
            : false;
        $this->declineReason = isset($raw['decline_reason']) && is_string($raw['decline_reason'])
            ? $raw['decline_reason']
            : null;
        $this->declineStatus = isset($raw['decline_status']) && is_string($raw['decline_status'])
            ? $raw['decline_status']
            : null;
        $this->receiver = is_array($raw['receiver'] ?? null) ? PayoutReceiver::fromArray($raw['receiver']) : null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getState(): int
    {
        return $this->state;
    }

    public function isWaiting(): bool
    {
        return $this->state === 0;
    }

    public function isProcessed(): bool
    {
        return $this->state === 1;
    }

    public function isFailed(): bool
    {
        return $this->state === -1;
    }

    public function getNet(): Money
    {
        return $this->net;
    }

    public function getFee(): Money
    {
        return $this->fee;
    }

    public function getGross(): Money
    {
        return $this->gross;
    }

    public function getCreationDate(): ?string
    {
        return $this->creationDate;
    }

    public function isDirectSettlement(): bool
    {
        return $this->directSettlement;
    }

    public function getNrb(): ?string
    {
        return $this->nrb;
    }

    public function isDeclined(): bool
    {
        return $this->declined;
    }

    public function getDeclineReason(): ?string
    {
        return $this->declineReason;
    }

    public function getDeclineStatus(): ?string
    {
        return $this->declineStatus;
    }

    public function getReceiver(): ?PayoutReceiver
    {
        return $this->receiver;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

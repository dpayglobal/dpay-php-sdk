<?php

declare(strict_types=1);

namespace DPay\Payout;

use DPay\Currency;
use DPay\Money;

/**
 * Payout header: `withdraws/details`, and `settlement` of `withdraws/details/full` (PayoutSettlement). Amounts are
 * in getCurrency() - `currency_code` when the API sends it, otherwise PLN.
 */
final class PayoutDetails
{
    /** @var array<mixed> */
    private array $raw;

    private string $currency;

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
        $currency = $raw['currency_code'] ?? null;
        $this->currency = is_string($currency) && Currency::isValid($currency) ? $currency : Currency::PLN;
        $this->id = isset($raw['id']) && is_scalar($raw['id']) ? (int) $raw['id'] : 0;
        $this->state = isset($raw['state']) && is_scalar($raw['state']) ? (int) $raw['state'] : 0;
        $zero = Money::of(0, $this->currency);
        $this->net = Money::tryFromApiNumber($raw['net'] ?? 0, $this->currency) ?? $zero;
        $this->fee = Money::tryFromApiNumber($raw['fee'] ?? 0, $this->currency) ?? $zero;
        $this->gross = Money::tryFromApiNumber($raw['gross'] ?? 0, $this->currency) ?? $zero;
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
        $this->receiver = is_array($raw['receiver'] ?? null) ? PayoutReceiver::fromArray($raw['receiver'], $this->currency) : null;
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
        return $this->state === PayoutState::WAITING;
    }

    public function isProcessed(): bool
    {
        return $this->state === PayoutState::PROCESSED;
    }

    public function isFailed(): bool
    {
        return $this->state === PayoutState::FAILED;
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

    /** Currency of the amounts (`currency_code`; PLN when the API does not send it). */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCreationDate(): ?string
    {
        return $this->creationDate;
    }

    /** When the transfer was sent to the bank (`withdraws/details/full` only); null until then. */
    public function getExportDate(): ?string
    {
        $exportDate = $this->raw['export_date'] ?? null;

        return is_string($exportDate) ? $exportDate : null;
    }

    /** Paysafecard payout (`paysafecard` = 1, present only for regular, non-direct settlements). */
    public function isPaysafecard(): bool
    {
        $paysafecard = $this->raw['paysafecard'] ?? null;

        return is_scalar($paysafecard) && (int) $paysafecard === 1;
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

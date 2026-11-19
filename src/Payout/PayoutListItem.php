<?php

declare(strict_types=1);

namespace DPay\Payout;

use DPay\Currency;
use DPay\Money;

/** One payout of `payouts->list()`. The account, receiver and decline reason are in payouts->details(getId()). */
final class PayoutListItem
{
    /** @var array<mixed> */
    private array $raw;

    private string $currency;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw)
    {
        $this->raw = $raw;
        $currency = $raw['currency_code'] ?? null;
        $this->currency = is_string($currency) && Currency::isValid($currency) ? $currency : Currency::PLN;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /** Use as `withdraw_id` in payouts->details() and payouts->detailsFull(). */
    public function getId(): int
    {
        return $this->int('id');
    }

    /** standard, direct or paysafecard (PayoutType). */
    public function getType(): ?string
    {
        $type = $this->raw['type'] ?? null;

        return is_string($type) ? $type : null;
    }

    /** 0 waiting, 1 processed, -1 failure (PayoutState). */
    public function getState(): int
    {
        return $this->int('state');
    }

    public function isWaiting(): bool
    {
        return $this->getState() === PayoutState::WAITING;
    }

    public function isProcessed(): bool
    {
        return $this->getState() === PayoutState::PROCESSED;
    }

    public function isFailed(): bool
    {
        return $this->getState() === PayoutState::FAILED;
    }

    public function getNet(): Money
    {
        return $this->money('net');
    }

    public function getFee(): Money
    {
        return $this->money('fee');
    }

    public function getGross(): Money
    {
        return $this->money('gross');
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCreationDate(): ?string
    {
        return $this->string('creation_date');
    }

    /** When the transfer was sent to the bank; null until then. */
    public function getExportDate(): ?string
    {
        return $this->string('export_date');
    }

    /** `declined` is the JSON integer 1. */
    public function isDeclined(): bool
    {
        return ($this->raw['declined'] ?? null) === 1;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    private function money(string $key): Money
    {
        return Money::tryFromApiNumber($this->raw[$key] ?? 0, $this->currency) ?? Money::of(0, $this->currency);
    }

    private function int(string $key): int
    {
        $value = $this->raw[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(string $key): ?string
    {
        $value = $this->raw[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}

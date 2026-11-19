<?php

declare(strict_types=1);

namespace DPay\Payout;

use DPay\Money;

/**
 * `payouts->detailsFull()`: the payout header, the totals of the WHOLE settlement and one page of the payments it
 * settles. A payout of exactly one payment returns one position; a payout with unknown positions returns none.
 */
final class PayoutSettlement
{
    /** @var array<mixed> */
    private array $raw;

    private PayoutDetails $settlement;

    /** @var array<int, SettlementPosition> */
    private array $positions;

    private PageMeta $meta;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw)
    {
        $this->raw = $raw;
        $this->settlement = PayoutDetails::fromArray(is_array($raw['settlement'] ?? null) ? $raw['settlement'] : []);
        $currency = $this->settlement->getCurrency();
        $this->positions = [];
        foreach (is_array($raw['transactions'] ?? null) ? $raw['transactions'] : [] as $position) {
            if (is_array($position)) {
                $this->positions[] = SettlementPosition::fromArray($position, $currency);
            }
        }
        $this->meta = PageMeta::fromArray(is_array($raw['meta'] ?? null) ? $raw['meta'] : []);
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /** Payout header (state, amounts, currency, account or receiver, decline reason). */
    public function getSettlement(): PayoutDetails
    {
        return $this->settlement;
    }

    /** Net total of the whole settlement. */
    public function getSummaryNet(): Money
    {
        return $this->summaryMoney('net');
    }

    public function getSummaryFee(): Money
    {
        return $this->summaryMoney('fee');
    }

    public function getSummaryGross(): Money
    {
        return $this->summaryMoney('gross');
    }

    /** Number of positions in the whole settlement. */
    public function getSummaryCount(): int
    {
        $count = $this->summary()['count'] ?? null;

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * Positions of this page.
     *
     * @return array<int, SettlementPosition>
     */
    public function getPositions(): array
    {
        return $this->positions;
    }

    public function getMeta(): PageMeta
    {
        return $this->meta;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * @return array<mixed>
     */
    private function summary(): array
    {
        return is_array($this->raw['summary'] ?? null) ? $this->raw['summary'] : [];
    }

    private function summaryMoney(string $key): Money
    {
        $currency = $this->settlement->getCurrency();

        return Money::tryFromApiNumber($this->summary()[$key] ?? 0, $currency) ?? Money::of(0, $currency);
    }
}

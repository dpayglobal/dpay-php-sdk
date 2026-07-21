<?php

declare(strict_types=1);

namespace DPay\Card;

use DPay\Money;
use InvalidArgumentException;

final class CardRecurringRegistration
{
    private string $label;

    private ?string $frequency = null;

    private ?Money $limitAmt = null;

    private ?Money $totLimitAmt = null;

    private ?bool $limitAmtFixed = null;

    private ?string $expirationDate = null;

    private ?string $initDate = null;

    private function __construct(string $label)
    {
        if ($label === '' || mb_strlen($label) > 50) {
            throw new InvalidArgumentException('Mandate label must be 1-50 characters');
        }
        $this->label = $label;
    }

    public static function create(string $label): self
    {
        return new self($label);
    }

    public function withFrequency(string $frequency): self
    {
        CardRecurringFrequency::assertValid($frequency);
        $this->frequency = $frequency;

        return $this;
    }

    public function withLimitAmt(Money $limitAmt): self
    {
        $this->limitAmt = $limitAmt;

        return $this;
    }

    public function withTotLimitAmt(Money $totLimitAmt): self
    {
        $this->totLimitAmt = $totLimitAmt;

        return $this;
    }

    public function withLimitAmtFixed(bool $fixed): self
    {
        $this->limitAmtFixed = $fixed;

        return $this;
    }

    public function withExpirationDate(string $expirationDate): self
    {
        $this->expirationDate = $this->assertDate($expirationDate);

        return $this;
    }

    public function withInitDate(string $initDate): self
    {
        $this->initDate = $this->assertDate($initDate);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['label' => $this->label];
        if ($this->frequency !== null) {
            $data['frequency'] = $this->frequency;
        }
        if ($this->limitAmt !== null) {
            $data['limit_amt'] = $this->limitAmt->getMinor();
        }
        if ($this->totLimitAmt !== null) {
            $data['tot_limit_amt'] = $this->totLimitAmt->getMinor();
        }
        if ($this->limitAmtFixed !== null) {
            $data['is_limit_amt_fixed'] = $this->limitAmtFixed;
        }
        if ($this->expirationDate !== null) {
            $data['expiration_date'] = $this->expirationDate;
        }
        if ($this->initDate !== null) {
            $data['init_date'] = $this->initDate;
        }

        return $data;
    }

    private function assertDate(string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new InvalidArgumentException(sprintf('Date "%s" must be in YYYY-MM-DD format', $date));
        }

        return $date;
    }
}

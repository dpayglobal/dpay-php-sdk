<?php

declare(strict_types=1);

namespace DPay\Blik;

use DPay\Money;
use InvalidArgumentException;

final class BlikRecurringRegistration
{
    private string $label;

    private string $model;

    private string $frequency;

    private ?Money $value = null;

    private ?int $limitAmt = null;

    private ?int $totLimitAmt = null;

    private ?bool $limitAmtFixed = null;

    private ?string $expirationDate = null;

    private ?string $initDate = null;

    private function __construct(string $label, string $model, string $frequency)
    {
        if ($label === '' || mb_strlen($label) > 50) {
            throw new InvalidArgumentException('Alias label must be 1-50 characters');
        }
        if (!in_array($model, ['A', 'M', 'O'], true)) {
            throw new InvalidArgumentException(sprintf('Invalid recurring model "%s"', $model));
        }
        if (preg_match('/^[1-9][0-9]{0,2}[DWMQY]$/', $frequency) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid recurring frequency "%s"', $frequency));
        }
        $this->label = $label;
        $this->model = $model;
        $this->frequency = $frequency;
    }

    public static function create(string $label, string $model, string $frequency): self
    {
        return new self($label, $model, $frequency);
    }

    public function withValue(Money $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function withLimitAmt(int $limitAmt): self
    {
        $this->limitAmt = $limitAmt;

        return $this;
    }

    public function withTotLimitAmt(int $totLimitAmt): self
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

    private function assertDate(string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new InvalidArgumentException(sprintf('Date "%s" must be in YYYY-MM-DD format', $date));
        }

        return $date;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'label' => $this->label,
            'type' => BlikAliasType::PAYID,
            'model' => $this->model,
            'frequency' => $this->frequency,
        ];
        if ($this->value !== null) {
            $data['value'] = $this->value->toDecimal();
        }
        if ($this->limitAmt !== null) {
            $data['limit_amt'] = $this->limitAmt;
        }
        if ($this->totLimitAmt !== null) {
            $data['tot_limit_amt'] = $this->totLimitAmt;
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
}

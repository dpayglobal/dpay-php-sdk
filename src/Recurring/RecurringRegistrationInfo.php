<?php

declare(strict_types=1);

namespace DPay\Recurring;

/**
 * Terms of a registered recurring payment, as returned by the status endpoint. Amounts in minor units (grosz).
 */
final class RecurringRegistrationInfo
{
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

    /** transactionId of the registering payment. */
    public function getTransactionId(): ?string
    {
        return $this->string('transaction_id');
    }

    public function getLabel(): ?string
    {
        return $this->string('label');
    }

    public function getModel(): ?string
    {
        return $this->string('model');
    }

    public function getFrequency(): ?string
    {
        return $this->string('frequency');
    }

    public function getLimitAmt(): ?int
    {
        return $this->int('limit_amt');
    }

    public function getTotLimitAmt(): ?int
    {
        return $this->int('tot_limit_amt');
    }

    public function isLimitAmtFixed(): ?bool
    {
        $value = $this->raw['is_limit_amt_fixed'] ?? null;

        return is_bool($value) ? $value : null;
    }

    public function getInitDate(): ?string
    {
        return $this->string('init_date');
    }

    public function getTermsUrl(): ?string
    {
        return $this->string('terms_url');
    }

    public function getTermsVersion(): ?string
    {
        return $this->string('terms_version');
    }

    /** ISO 8601 with offset. */
    public function getRegisteredAt(): ?string
    {
        return $this->string('registered_at');
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    private function string(string $key): ?string
    {
        return isset($this->raw[$key]) && is_string($this->raw[$key]) ? $this->raw[$key] : null;
    }

    private function int(string $key): ?int
    {
        $value = $this->raw[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }
}

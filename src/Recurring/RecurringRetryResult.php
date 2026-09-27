<?php

declare(strict_types=1);

namespace DPay\Recurring;

/**
 * Result of retrying a declined recurring charge. `pending` - the retry went to the bank, the outcome comes like for
 * a charge (webhook, IPN, status); `failed` - the bank declined it at once (error code in getErrorCode()).
 */
final class RecurringRetryResult
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SUCCESS = 'success';

    /** @var array<mixed> */
    private array $raw;

    /** @var array<mixed> */
    private array $retry;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw)
    {
        $this->raw = $raw;
        $this->retry = is_array($raw['retry'] ?? null) ? $raw['retry'] : [];
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getTransactionId(): string
    {
        $id = $this->raw['transactionId'] ?? null;

        return is_scalar($id) ? (string) $id : '';
    }

    public function getStatus(): ?string
    {
        $status = $this->retry['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    public function isPending(): bool
    {
        return $this->getStatus() === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->getStatus() === self::STATUS_FAILED;
    }

    /** Which retry this was (1-3). */
    public function getCount(): ?int
    {
        $count = $this->retry['count'] ?? null;

        return is_int($count) ? $count : null;
    }

    public function getErrorCode(): ?string
    {
        $error = $this->retry['error'] ?? null;

        return is_string($error) ? $error : null;
    }

    public function getErrorDescription(): ?string
    {
        $description = $this->retry['error_description'] ?? null;

        return is_string($description) ? $description : null;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

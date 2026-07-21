<?php

declare(strict_types=1);

namespace DPay\Payment;

final class RegisteredPayment
{
    /** @var array<mixed> */
    private array $raw;

    private string $transactionId;

    private string $message;

    private ?string $ipksef;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw, string $transactionId, string $message, ?string $ipksef)
    {
        $this->raw = $raw;
        $this->transactionId = $transactionId;
        $this->message = $message;
        $this->ipksef = $ipksef;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $transactionId = $data['transactionId'] ?? null;
        $message = $data['msg'] ?? null;

        return new self(
            $data,
            is_scalar($transactionId) ? (string) $transactionId : '',
            is_scalar($message) ? (string) $message : '',
            isset($data['ipksef']) && is_string($data['ipksef']) ? $data['ipksef'] : null
        );
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getRedirectUrl(): ?string
    {
        return preg_match('#^https?://#', $this->message) === 1 ? $this->message : null;
    }

    public function isPaid(): bool
    {
        return $this->message === 'Transaction paid';
    }

    public function isInlineProcessing(): bool
    {
        return $this->message === 'Internal processing';
    }

    public function getIpksef(): ?string
    {
        return $this->ipksef;
    }

    public function getCardRecurringAlias(): ?string
    {
        $additional = $this->raw['additionalInfo'] ?? null;
        if (is_array($additional) && isset($additional['card_recurring_alias']) && is_string($additional['card_recurring_alias'])) {
            return $additional['card_recurring_alias'];
        }

        return null;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

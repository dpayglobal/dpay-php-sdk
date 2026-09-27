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

    /** Alias of the recurring payment registered with this payment (withRecurringRegistration). */
    public function getRecurringAlias(): ?string
    {
        $registration = $this->recurringRegistration();

        return isset($registration['alias']) && is_string($registration['alias']) ? $registration['alias'] : null;
    }

    /**
     * @return array<int, string>
     */
    public function getRecurringMethods(): array
    {
        $methods = $this->recurringRegistration()['methods'] ?? null;

        return is_array($methods) ? array_values(array_filter($methods, 'is_string')) : [];
    }

    /**
     * @return array<mixed>
     */
    private function recurringRegistration(): array
    {
        $additional = $this->raw['additionalInfo'] ?? null;
        $registration = is_array($additional) ? ($additional['recurring_registration'] ?? null) : null;

        return is_array($registration) ? $registration : [];
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

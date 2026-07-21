<?php

declare(strict_types=1);

namespace DPay\Payment;

use DPay\Currency;
use DPay\Money;

final class TransactionRefund
{
    /** @var array<mixed> */
    private array $raw;

    private string $paymentId;

    private Money $value;

    private string $status;

    private ?string $creationDate;

    private ?string $paymentDate;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(
        array $raw,
        string $paymentId,
        Money $value,
        string $status,
        ?string $creationDate,
        ?string $paymentDate
    ) {
        $this->raw = $raw;
        $this->paymentId = $paymentId;
        $this->value = $value;
        $this->status = $status;
        $this->creationDate = $creationDate;
        $this->paymentDate = $paymentDate;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            isset($data['payment_id']) && is_scalar($data['payment_id']) ? (string) $data['payment_id'] : '',
            Money::tryFromApiNumber($data['value'] ?? 0, Currency::PLN) ?? Money::pln(0),
            isset($data['status']) && is_scalar($data['status']) ? (string) $data['status'] : TransactionStatus::PAID,
            isset($data['creation_date']) && is_scalar($data['creation_date']) ? (string) $data['creation_date'] : null,
            isset($data['payment_date']) && is_scalar($data['payment_date']) ? (string) $data['payment_date'] : null
        );
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getValue(): Money
    {
        return $this->value;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreationDate(): ?string
    {
        return $this->creationDate;
    }

    public function getPaymentDate(): ?string
    {
        return $this->paymentDate;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

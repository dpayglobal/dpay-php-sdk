<?php

declare(strict_types=1);

namespace DPay\Payment;

use DPay\Currency;
use DPay\Money;

final class Transaction
{
    /** @var array<mixed> */
    private array $raw;

    private string $id;

    private Money $value;

    private string $status;

    private ?string $paymentMethod;

    private ?string $creationDate;

    private ?string $paymentDate;

    private bool $settled;

    private bool $refunded;

    private Money $refundedAmount;

    private Money $availableRefundAmount;

    private bool $fullyRefunded;

    private bool $direct;

    private ?string $gatewayId;

    /** @var array<mixed> */
    private array $payer;

    /** @var array<int, TransactionRefund> */
    private array $refunds;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw)
    {
        $this->raw = $raw;
        $tx = is_array($raw['transaction'] ?? null) ? $raw['transaction'] : [];

        $this->id = isset($tx['id']) && is_scalar($tx['id']) ? (string) $tx['id'] : '';
        $this->value = Money::tryFromApiNumber($tx['value'] ?? 0, Currency::PLN) ?? Money::pln(0);
        $this->status = isset($tx['status']) && is_scalar($tx['status']) ? (string) $tx['status'] : '';
        $this->paymentMethod = isset($tx['payment_method']) && is_scalar($tx['payment_method']) ? (string) $tx['payment_method'] : null;
        $this->creationDate = isset($tx['creation_date']) && is_scalar($tx['creation_date']) ? (string) $tx['creation_date'] : null;
        $this->paymentDate = isset($tx['payment_date']) && is_string($tx['payment_date']) ? $tx['payment_date'] : null;
        $this->settled = (bool) ($tx['settled'] ?? false);
        $this->refunded = (bool) ($tx['refunded'] ?? false);
        $this->refundedAmount = Money::tryFromApiNumber($tx['refunded_amount'] ?? 0, Currency::PLN) ?? Money::pln(0);
        $this->availableRefundAmount = Money::tryFromApiNumber($tx['available_refund_amount'] ?? 0, Currency::PLN) ?? Money::pln(0);
        $this->fullyRefunded = (bool) ($tx['fully_refunded'] ?? false);
        $this->direct = (bool) ($tx['direct'] ?? false);
        $this->gatewayId = isset($tx['gateway_id']) && is_string($tx['gateway_id']) ? $tx['gateway_id'] : null;
        $payerData = is_array($raw['payer'] ?? null) ? $raw['payer'] : [];
        $this->payer = $payerData;

        $this->refunds = [];
        $refundsData = is_array($raw['refunds'] ?? null) ? $raw['refunds'] : [];
        foreach ($refundsData as $refund) {
            if (is_array($refund)) {
                $this->refunds[] = TransactionRefund::fromArray($refund);
            }
        }
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getValue(): Money
    {
        return $this->value;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isPaid(): bool
    {
        return $this->status === TransactionStatus::PAID || $this->status === TransactionStatus::CAPTURED;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function getCreationDate(): ?string
    {
        return $this->creationDate;
    }

    public function getPaymentDate(): ?string
    {
        return $this->paymentDate;
    }

    public function isSettled(): bool
    {
        return $this->settled;
    }

    public function isRefunded(): bool
    {
        return $this->refunded;
    }

    public function getRefundedAmount(): Money
    {
        return $this->refundedAmount;
    }

    public function getAvailableRefundAmount(): Money
    {
        return $this->availableRefundAmount;
    }

    public function isFullyRefunded(): bool
    {
        return $this->fullyRefunded;
    }

    public function isDirect(): bool
    {
        return $this->direct;
    }

    public function getGatewayId(): ?string
    {
        return $this->gatewayId;
    }

    /**
     * @return array<mixed>
     */
    public function getPayer(): array
    {
        return $this->payer;
    }

    /**
     * @return array<int, TransactionRefund>
     */
    public function getRefunds(): array
    {
        return $this->refunds;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

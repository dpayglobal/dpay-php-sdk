<?php

declare(strict_types=1);

namespace DPay\Payment;

use DPay\Money;
use InvalidArgumentException;

final class InvoiceDetails
{
    private ?string $payerNip = null;

    private ?string $payerName = null;

    private ?string $invoiceNumber = null;

    private ?string $paymentDueDate = null;

    private ?Money $vatAmount = null;

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function withPayerNip(string $payerNip): self
    {
        $this->payerNip = $payerNip;

        return $this;
    }

    public function withPayerName(string $payerName): self
    {
        $this->payerName = $payerName;

        return $this;
    }

    public function withInvoiceNumber(string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }

    public function withPaymentDueDate(string $paymentDueDate): self
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDueDate) !== 1) {
            throw new InvalidArgumentException('Payment due date must be in YYYY-MM-DD format');
        }
        $this->paymentDueDate = $paymentDueDate;

        return $this;
    }

    public function withVatAmount(Money $vatAmount): self
    {
        $this->vatAmount = $vatAmount;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];
        if ($this->payerNip !== null) {
            $data['payer_nip'] = $this->payerNip;
        }
        if ($this->payerName !== null) {
            $data['payer_name'] = $this->payerName;
        }
        if ($this->invoiceNumber !== null) {
            $data['invoice_number'] = $this->invoiceNumber;
        }
        if ($this->paymentDueDate !== null) {
            $data['payment_due_date'] = $this->paymentDueDate;
        }
        if ($this->vatAmount !== null) {
            $data['vat_amount'] = $this->vatAmount->getMinor();
        }

        return $data;
    }
}

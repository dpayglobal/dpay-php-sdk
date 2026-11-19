<?php

declare(strict_types=1);

namespace DPay\Payment;

use DPay\Currency;
use DPay\Money;

/**
 * The transaction returned with a registration answered "Transaction paid" (`additionalInfo.transaction`) - the
 * payment was already paid (e.g. a repeated registration). Its statuses differ from Transaction::getStatus():
 * pending, paid, captured, canceled.
 */
final class RegisteredTransaction
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_CANCELED = 'canceled';

    /** @var array<mixed> */
    private array $raw;

    private string $currency;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw, string $currency)
    {
        $this->raw = $raw;
        $this->currency = $currency;
    }

    /**
     * @param array<mixed> $data
     * @param string $currency currency of the registered amount (the object carries none)
     */
    public static function fromArray(array $data, string $currency = Currency::PLN): self
    {
        return new self($data, Currency::isValid($currency) ? $currency : Currency::PLN);
    }

    public function getPaymentId(): string
    {
        return $this->string('payment_id') ?? '';
    }

    public function getStatus(): ?string
    {
        return $this->string('status');
    }

    public function isPaid(): bool
    {
        return in_array($this->getStatus(), [self::STATUS_PAID, self::STATUS_CAPTURED], true);
    }

    public function getValue(): ?Money
    {
        return Money::tryFromApiNumber($this->raw['value'] ?? null, $this->currency);
    }

    public function getEmail(): ?string
    {
        return $this->string('email');
    }

    public function getDescription(): ?string
    {
        return $this->string('description');
    }

    public function getName(): ?string
    {
        return $this->string('name');
    }

    public function getSurname(): ?string
    {
        return $this->string('surname');
    }

    /** Provider transaction identifier, when known. */
    public function getPid(): ?string
    {
        return $this->string('pid');
    }

    public function getMtid(): ?string
    {
        return $this->string('mtid');
    }

    public function getCreationDate(): ?string
    {
        return $this->string('creation_date');
    }

    public function getPaymentDate(): ?string
    {
        return $this->string('payment_date');
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
        $value = $this->raw[$key] ?? null;

        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}

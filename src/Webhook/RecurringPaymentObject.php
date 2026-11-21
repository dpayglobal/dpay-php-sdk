<?php

declare(strict_types=1);

namespace DPay\Webhook;

/** The `recurring_payment` object of recurring_payment.* events, identified by the registration payment. */
final class RecurringPaymentObject
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_DECLINED = 'declined';

    public const CANCELED_BY_MERCHANT = 'merchant';
    public const CANCELED_BY_CUSTOMER = 'customer';
    public const CANCELED_BY_DPAY = 'dpay';

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

    /** ID of the registration payment. */
    public function getId(): string
    {
        return ObjectFields::string($this->raw, 'id') ?? '';
    }

    /** The alias you charge the recurring payment with. */
    public function getAlias(): ?string
    {
        return ObjectFields::string($this->raw, 'alias');
    }

    public function getStatus(): ?string
    {
        return ObjectFields::string($this->raw, 'status');
    }

    /** merchant, customer or dpay when canceled; null in other states. */
    public function getCanceledBy(): ?string
    {
        return ObjectFields::string($this->raw, 'canceled_by');
    }

    /**
     * @return array<mixed>|null
     */
    public function getPaymentMethod(): ?array
    {
        return ObjectFields::arrayOrNull($this->raw, 'payment_method');
    }

    public function getPaymentMethodType(): ?string
    {
        return ObjectFields::string($this->getPaymentMethod() ?? [], 'type');
    }

    /** Recurring model: A, M or O (BLIK). */
    public function getModel(): ?string
    {
        return ObjectFields::string($this->terms(), 'model');
    }

    /** e.g. `1M`. */
    public function getFrequency(): ?string
    {
        return ObjectFields::string($this->terms(), 'frequency');
    }

    /** Single charge limit in minor units. */
    public function getLimitAmount(): ?int
    {
        return ObjectFields::intOrNull($this->terms(), 'limit_amount');
    }

    /** Total limit in minor units. */
    public function getTotalLimitAmount(): ?int
    {
        return ObjectFields::intOrNull($this->terms(), 'total_limit_amount');
    }

    /** YYYY-MM-DD. */
    public function getStartsOn(): ?string
    {
        return ObjectFields::string($this->terms(), 'starts_on');
    }

    /** YYYY-MM-DD. */
    public function getExpiresOn(): ?string
    {
        return ObjectFields::string($this->terms(), 'expires_on');
    }

    public function getCreated(): ?string
    {
        return ObjectFields::string($this->raw, 'created');
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * @return array<mixed>
     */
    private function terms(): array
    {
        return ObjectFields::arrayOrNull($this->raw, 'terms') ?? [];
    }
}

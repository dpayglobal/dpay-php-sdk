<?php

declare(strict_types=1);

namespace DPay\Webhook;

/**
 * Webhook event envelope: {id, type, api_version, created, livemode, service, [merchant_ref], data: {object}}.
 * `data.object` is available as an array (getObject()) and as a typed model of its kind: getPayment(), getRefund(),
 * getRecurringPayment(), getPayout() - amounts in minor units.
 */
final class WebhookEvent
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

    public function getId(): string
    {
        return $this->string('id') ?? '';
    }

    public function getType(): string
    {
        return $this->string('type') ?? '';
    }

    public function getApiVersion(): ?string
    {
        return $this->string('api_version');
    }

    /** Event time in UTC (`YYYY-MM-DDTHH:MM:SSZ`) - not the delivery time, do not use it against replays. */
    public function getCreated(): ?string
    {
        return $this->string('created');
    }

    public function isLivemode(): bool
    {
        return ($this->raw['livemode'] ?? true) !== false;
    }

    /** Service name, `null` for account events (payouts) and test events. */
    public function getService(): ?string
    {
        return $this->string('service');
    }

    /** Merchant reference, present only in events sent to a dpay Connect partner. */
    public function getMerchantRef(): ?string
    {
        return $this->string('merchant_ref');
    }

    /**
     * @return array<mixed>
     */
    public function getObject(): array
    {
        $data = $this->raw['data'] ?? null;
        $object = is_array($data) ? ($data['object'] ?? null) : null;

        return is_array($object) ? $object : [];
    }

    /** `payment`, `refund`, `recurring_payment`, `payout` or `webhook_endpoint`. */
    public function getObjectType(): ?string
    {
        $object = $this->getObject();

        return isset($object['object']) && is_string($object['object']) ? $object['object'] : null;
    }

    /** The `payment` object of payment.* events; null for other objects. */
    public function getPayment(): ?PaymentObject
    {
        return $this->getObjectType() === 'payment' ? PaymentObject::fromArray($this->getObject()) : null;
    }

    /** The `refund` object of refund.* events; null for other objects. */
    public function getRefund(): ?RefundObject
    {
        return $this->getObjectType() === 'refund' ? RefundObject::fromArray($this->getObject()) : null;
    }

    /** The `recurring_payment` object of recurring_payment.* events; null for other objects. */
    public function getRecurringPayment(): ?RecurringPaymentObject
    {
        return $this->getObjectType() === 'recurring_payment' ? RecurringPaymentObject::fromArray($this->getObject()) : null;
    }

    /** The `payout` object of payout.* events; null for other objects. */
    public function getPayout(): ?PayoutObject
    {
        return $this->getObjectType() === 'payout' ? PayoutObject::fromArray($this->getObject()) : null;
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
}

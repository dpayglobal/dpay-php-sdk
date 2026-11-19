<?php

declare(strict_types=1);

namespace DPay\Webhook;

use DPay\Money;

/** The `payment` object of payment.* events. Amounts in minor units of getCurrency(), times in UTC (ISO 8601). */
final class PaymentObject
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVERSED = 'reversed';
    public const STATUS_CHARGED_BACK = 'charged_back';

    public const SEQUENCE_FIRST = 'first';
    public const SEQUENCE_SUBSEQUENT = 'subsequent';

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

    /** Payment ID - the transactionId of the registration. */
    public function getId(): string
    {
        return ObjectFields::string($this->raw, 'id') ?? '';
    }

    public function getStatus(): ?string
    {
        return ObjectFields::string($this->raw, 'status');
    }

    public function getCurrency(): string
    {
        return ObjectFields::currency($this->raw);
    }

    public function getAmount(): Money
    {
        return ObjectFields::money($this->raw, 'amount', $this->getCurrency());
    }

    public function getAmountRefunded(): Money
    {
        return ObjectFields::money($this->raw, 'amount_refunded', $this->getCurrency());
    }

    /** Total captured from a card pre-authorisation. */
    public function getAmountCaptured(): Money
    {
        return ObjectFields::money($this->raw, 'amount_captured', $this->getCurrency());
    }

    public function getDescription(): ?string
    {
        return ObjectFields::string($this->raw, 'description');
    }

    public function getCreated(): ?string
    {
        return ObjectFields::string($this->raw, 'created');
    }

    /** Null until the payment is paid. */
    public function getPaidAt(): ?string
    {
        return ObjectFields::string($this->raw, 'paid_at');
    }

    /**
     * `{type, <type>: {details}}`, e.g. `{"type": "blik", "blik": {"flow": "code"}}`; null while unknown.
     *
     * @return array<mixed>|null
     */
    public function getPaymentMethod(): ?array
    {
        return ObjectFields::arrayOrNull($this->raw, 'payment_method');
    }

    /** blik, card, bank_transfer, twisto, multibanco, blik_pay_later, mb_way, paypal, paypo, paysafecard. */
    public function getPaymentMethodType(): ?string
    {
        return ObjectFields::string($this->getPaymentMethod() ?? [], 'type');
    }

    /** Decline reason in payment.failed. */
    public function getFailure(): ?Failure
    {
        $failure = ObjectFields::arrayOrNull($this->raw, 'failure');

        return $failure === null ? null : Failure::fromArray($failure);
    }

    public function isRecurring(): bool
    {
        return ObjectFields::arrayOrNull($this->raw, 'recurring') !== null;
    }

    /** Alias of the recurring payment (BLIK or card) this payment belongs to. */
    public function getRecurringAlias(): ?string
    {
        return ObjectFields::string(ObjectFields::arrayOrNull($this->raw, 'recurring') ?? [], 'alias');
    }

    /** ID of the payment that registered the recurring payment. */
    public function getRecurringRegistration(): ?string
    {
        return ObjectFields::string(ObjectFields::arrayOrNull($this->raw, 'recurring') ?? [], 'registration');
    }

    /** `first` (the registration payment) or `subsequent` (a charge). */
    public function getRecurringSequence(): ?string
    {
        return ObjectFields::string(ObjectFields::arrayOrNull($this->raw, 'recurring') ?? [], 'sequence');
    }

    /** Your `reference` from the registration. */
    public function getMerchantReference(): ?string
    {
        return ObjectFields::string(ObjectFields::arrayOrNull($this->raw, 'references') ?? [], 'merchant');
    }

    /** Transaction ID at the provider (BLIK, card processor), if available. */
    public function getProviderReference(): ?string
    {
        return ObjectFields::string(ObjectFields::arrayOrNull($this->raw, 'references') ?? [], 'provider');
    }

    public function getCustom(): ?string
    {
        return ObjectFields::string($this->raw, 'custom');
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

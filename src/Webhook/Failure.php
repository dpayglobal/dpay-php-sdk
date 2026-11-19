<?php

declare(strict_types=1);

namespace DPay\Webhook;

/** Decline reason of a webhook object (`failure`): payment.failed, refund.failed, payout.failed. */
final class Failure
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

    /** dpay decline code (DeclineCode), e.g. `insufficient_funds`. */
    public function getCode(): string
    {
        $code = $this->raw['code'] ?? null;

        return is_string($code) && $code !== '' ? $code : DeclineCode::GENERIC_DECLINE;
    }

    /** English description of the code. */
    public function getMessage(): ?string
    {
        $message = $this->raw['message'] ?? null;

        return is_string($message) ? $message : null;
    }

    /** customer, merchant, risk or system (FailureCategory). */
    public function getCategory(): ?string
    {
        $category = $this->raw['category'] ?? null;

        return is_string($category) ? $category : null;
    }

    /** Whether the same payment may succeed when tried again (e.g. after topping up the account). */
    public function isRetryable(): bool
    {
        return ($this->raw['retryable'] ?? false) === true;
    }

    /** Original provider code (e.g. a BLIK code), or for payout_failed what happened to the funds. */
    public function getProviderCode(): ?string
    {
        $providerCode = $this->raw['provider_code'] ?? null;

        return is_string($providerCode) || is_int($providerCode) ? (string) $providerCode : null;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

<?php

declare(strict_types=1);

namespace DPay\Ipn;

final class IpnEvent
{
    public const ACK = 'OK';

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
     * @internal
     * @param array<mixed> $payload
     */
    public static function fromVerifiedPayload(array $payload): self
    {
        return new self($payload);
    }

    public function getId(): string
    {
        $id = $this->raw['id'] ?? null;
        return isset($id) && is_scalar($id) ? (string) $id : '';
    }

    public function getAmount(): string
    {
        $amount = $this->raw['amount'] ?? null;
        return isset($amount) && is_scalar($amount) ? (string) $amount : '';
    }

    public function getEmail(): ?string
    {
        $email = $this->raw['email'] ?? null;
        return isset($email) && is_scalar($email) ? (string) $email : null;
    }

    public function getType(): string
    {
        $type = $this->raw['type'] ?? null;
        return isset($type) && is_scalar($type) ? (string) $type : '';
    }

    public function isTransfer(): bool
    {
        return $this->getType() === IpnType::TRANSFER;
    }

    /** @deprecated dpay no longer sends capture IPNs - use the `payment.captured` webhook event. */
    public function isCapture(): bool
    {
        return $this->getType() === IpnType::CAPTURE;
    }

    public function isDcb(): bool
    {
        return $this->getType() === IpnType::DCB;
    }

    public function getAttempt(): int
    {
        $attempt = $this->raw['attempt'] ?? null;
        return isset($attempt) && is_numeric($attempt) ? (int) $attempt : 0;
    }

    public function getVersion(): int
    {
        $version = $this->raw['version'] ?? null;
        return isset($version) && is_numeric($version) ? (int) $version : 0;
    }

    public function getCustom(): ?string
    {
        $custom = $this->raw['custom'] ?? null;
        return isset($custom) && is_scalar($custom) ? (string) $custom : null;
    }

    /** @deprecated dpay no longer sends capture IPNs - use the `payment.captured` webhook event. */
    public function getCapturePaymentId(): ?string
    {
        $capturePaymentId = $this->raw['capture_payment_id'] ?? null;
        return isset($capturePaymentId) && is_scalar($capturePaymentId) ? (string) $capturePaymentId : null;
    }

    public function getSignature(): string
    {
        $signature = $this->raw['signature'] ?? null;
        return isset($signature) && is_scalar($signature) ? (string) $signature : '';
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

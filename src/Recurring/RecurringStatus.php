<?php

declare(strict_types=1);

namespace DPay\Recurring;

final class RecurringStatus
{
    public const ACTIVE = 'ACTIVE';
    public const INACTIVE = 'INACTIVE';
    public const UNREGISTERED = 'UNREGISTERED';
    public const EXPIRED = 'EXPIRED';
    public const DECLINED = 'DECLINED';

    /** @var array<mixed> */
    private array $raw;

    private ?RecurringRegistrationInfo $registration;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw)
    {
        $this->raw = $raw;
        $this->registration = is_array($raw['registration'] ?? null)
            ? RecurringRegistrationInfo::fromArray($raw['registration'])
            : null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getAlias(): string
    {
        return $this->string('alias') ?? '';
    }

    /** Payment method of the recurring payment, e.g. `blik`. */
    public function getMethod(): ?string
    {
        return $this->string('method');
    }

    /** ACTIVE, INACTIVE (waiting for the customer), UNREGISTERED, EXPIRED, DECLINED or null. */
    public function getStatus(): ?string
    {
        return $this->string('status');
    }

    public function isActive(): bool
    {
        return $this->getStatus() === self::ACTIVE;
    }

    public function getExpirationDate(): ?string
    {
        return $this->string('expiration_date');
    }

    public function getRegistration(): ?RecurringRegistrationInfo
    {
        return $this->registration;
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

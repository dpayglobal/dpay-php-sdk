<?php

declare(strict_types=1);

namespace DPay\Blik;

final class BlikRecurringStatus
{
    /** @var array<mixed> */
    private array $raw;

    private string $aliasValue;

    private string $aliasType;

    private ?string $status;

    private ?string $expirationDate;

    private ?BlikRecurringRegistrationInfo $registration;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw)
    {
        $this->raw = $raw;
        $this->aliasValue = is_scalar($raw['alias_value'] ?? null) ? (string) $raw['alias_value'] : '';
        $this->aliasType = is_scalar($raw['alias_type'] ?? null) ? (string) $raw['alias_type'] : BlikAliasType::PAYID;
        $this->status = isset($raw['status']) && is_string($raw['status']) ? $raw['status'] : null;
        $this->expirationDate = isset($raw['expiration_date']) && is_string($raw['expiration_date'])
            ? $raw['expiration_date']
            : null;
        $this->registration = is_array($raw['registration'] ?? null)
            ? BlikRecurringRegistrationInfo::fromArray($raw['registration'])
            : null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getAliasValue(): string
    {
        return $this->aliasValue;
    }

    public function getAliasType(): string
    {
        return $this->aliasType;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function getExpirationDate(): ?string
    {
        return $this->expirationDate;
    }

    public function getRegistration(): ?BlikRecurringRegistrationInfo
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
}

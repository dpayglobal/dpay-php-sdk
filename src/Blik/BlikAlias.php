<?php

declare(strict_types=1);

namespace DPay\Blik;

final class BlikAlias
{
    /** @var array<mixed> */
    private array $raw;

    private string $aliasValue;

    private string $aliasType;

    private ?string $status;

    private ?string $expirationDate;

    /** @var array<int, BlikApp> */
    private array $apps;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw)
    {
        $this->raw = $raw;
        $this->aliasValue = is_scalar($raw['alias_value'] ?? null) ? (string) $raw['alias_value'] : '';
        $this->aliasType = is_scalar($raw['alias_type'] ?? null) ? (string) $raw['alias_type'] : BlikAliasType::UID;
        $this->status = isset($raw['status']) && is_string($raw['status']) ? $raw['status'] : null;
        $this->expirationDate = isset($raw['expiration_date']) && is_string($raw['expiration_date'])
            ? $raw['expiration_date']
            : null;
        $this->apps = [];
        foreach (is_array($raw['apps'] ?? null) ? $raw['apps'] : [] as $app) {
            if (is_array($app)) {
                $this->apps[] = BlikApp::fromArray($app);
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

    /**
     * @return array<int, BlikApp>
     */
    public function getApps(): array
    {
        return $this->apps;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}

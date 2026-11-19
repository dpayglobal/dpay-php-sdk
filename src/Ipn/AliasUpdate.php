<?php

declare(strict_types=1);

namespace DPay\Ipn;

/**
 * Payload of an `alias_update` IPN: a BLIK OneClick alias (alias type UID) or a recurring payment (PAYID) changed
 * its status. Sent to `alias_ipn_url` from the registration, or to `url_ipn` without it.
 */
final class AliasUpdate
{
    public const CHANGE_REGISTER = 'ALIAS_REGISTER';
    public const CHANGE_UPDATE = 'ALIAS_UPDATE';
    public const CHANGE_UNREGISTER = 'ALIAS_UNREGISTER';
    public const CHANGE_EXPIRED = 'ALIAS_EXPIRED';
    public const CHANGE_DECLINED = 'ALIAS_DECLINED';

    public const ALIAS_TYPE_UID = 'UID';
    public const ALIAS_TYPE_PAYID = 'PAYID';

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_UNREGISTERED = 'UNREGISTERED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_DECLINED = 'DECLINED';

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

    /** ALIAS_REGISTER, ALIAS_UPDATE, ALIAS_UNREGISTER, ALIAS_EXPIRED or ALIAS_DECLINED. */
    public function getChangeType(): ?string
    {
        return $this->string('change_type');
    }

    /** UID (BLIK OneClick) or PAYID (recurring payment). */
    public function getAliasType(): ?string
    {
        return $this->string('alias_type');
    }

    /** ACTIVE, INACTIVE, UNREGISTERED, EXPIRED or DECLINED. */
    public function getAliasStatus(): ?string
    {
        return $this->string('alias_status');
    }

    public function isActive(): bool
    {
        return $this->getAliasStatus() === self::STATUS_ACTIVE;
    }

    /** Alias identifier at BLIK - not the alias value you send with payments. */
    public function getAliasKey(): ?string
    {
        return $this->string('alias_key');
    }

    public function getAliasLabel(): ?string
    {
        return $this->string('alias_label');
    }

    public function getAliasExpireAt(): ?string
    {
        return $this->string('alias_expire_at');
    }

    public function getChangeReasonCategory(): ?string
    {
        return $this->string('change_reason_category');
    }

    public function getChangeReasonMessage(): ?string
    {
        return $this->string('change_reason_message');
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

<?php

declare(strict_types=1);

namespace DPay\Payment;

/** Return and IPN addresses stored with a transaction (`transaction.urls` of payments->details()). */
final class TransactionUrls
{
    private ?string $success;

    private ?string $fail;

    private ?string $ipn;

    private function __construct(?string $success, ?string $fail, ?string $ipn)
    {
        $this->success = $success;
        $this->fail = $fail;
        $this->ipn = $ipn;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(self::string($data, 'success'), self::string($data, 'fail'), self::string($data, 'ipn'));
    }

    public function getSuccess(): ?string
    {
        return $this->success;
    }

    public function getFail(): ?string
    {
        return $this->fail;
    }

    /** Null when the payment was registered without an IPN address. */
    public function getIpn(): ?string
    {
        return $this->ipn;
    }

    /**
     * @param array<mixed> $data
     */
    private static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}

<?php

declare(strict_types=1);

namespace DPay\Card;

use InvalidArgumentException;

final class CardData
{
    private string $pan;

    private string $cvv;

    private string $expiry;

    public function __construct(string $pan, string $cvv, string $expiry)
    {
        $normalizedPan = str_replace(' ', '', $pan);
        if (preg_match('/^\d{12,19}$/', $normalizedPan) !== 1) {
            throw new InvalidArgumentException('Card number must be 12-19 digits');
        }
        if (preg_match('/^\d{3,4}$/', $cvv) !== 1) {
            throw new InvalidArgumentException('CVV must be 3-4 digits');
        }
        if (preg_match('#^(0[1-9]|1[0-2])/\d{2}$#', $expiry) !== 1) {
            throw new InvalidArgumentException('Expiry must be in MM/YY format');
        }
        $this->pan = $normalizedPan;
        $this->cvv = $cvv;
        $this->expiry = $expiry;
    }

    public function getPan(): string
    {
        return $this->pan;
    }

    public function getCvv(): string
    {
        return $this->cvv;
    }

    public function getExpiry(): string
    {
        return $this->expiry;
    }
}

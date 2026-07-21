<?php

declare(strict_types=1);

namespace DPay\Payment;

use DPay\Money;
use InvalidArgumentException;

final class PayoutPosition
{
    private string $iban;

    private string $title;

    private Money $amount;

    public function __construct(string $iban, string $title, Money $amount)
    {
        if ($iban === '') {
            throw new InvalidArgumentException('Payout IBAN must not be empty');
        }
        if ($title === '' || mb_strlen($title) > 255) {
            throw new InvalidArgumentException('Payout title must be 1-255 characters');
        }
        $this->iban = $iban;
        $this->title = $title;
        $this->amount = $amount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'iban' => $this->iban,
            'title' => $this->title,
            'amount' => (float) $this->amount->toDecimal(),
        ];
    }
}

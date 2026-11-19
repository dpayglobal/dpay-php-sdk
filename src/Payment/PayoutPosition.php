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

    /**
     * @param string $iban Polish IBAN of one of your verified bank accounts (`PL` + 26 digits, spaces allowed)
     * @param Money $amount above 0
     */
    public function __construct(string $iban, string $title, Money $amount)
    {
        if (!self::isValidPolishIban($iban)) {
            throw new InvalidArgumentException(sprintf('Payout IBAN "%s" must be a valid Polish IBAN (PL + 26 digits)', $iban));
        }
        if ($title === '' || mb_strlen($title) > 255) {
            throw new InvalidArgumentException('Payout title must be 1-255 characters');
        }
        if ($amount->getMinor() <= 0) {
            throw new InvalidArgumentException('Payout amount must be above 0');
        }
        $this->iban = $iban;
        $this->title = $title;
        $this->amount = $amount;
    }

    /**
     * `PL` + 26 digits after removing whitespace and upper-casing, with a valid ISO 13616 check (mod 97 = 1).
     */
    public static function isValidPolishIban(string $iban): bool
    {
        $normalized = strtoupper((string) preg_replace('/\s/', '', $iban));
        if (preg_match('/^PL\d{26}$/', $normalized) !== 1) {
            return false;
        }
        // Country code and check digits go to the end, letters become numbers (P = 25, L = 21)
        $numeric = substr($normalized, 4) . '2521' . substr($normalized, 2, 2);
        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ($remainder . $chunk) % 97;
        }

        return $remainder === 1;
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

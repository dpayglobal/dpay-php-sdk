<?php

declare(strict_types=1);

namespace DPay\Tests\Card;

use DPay\Card\CardRecurringFrequency;
use DPay\Card\CardRecurringRegistration;
use DPay\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CardRecurringRegistrationTest extends TestCase
{
    public function testToArraySerializesLimitsInGrosze(): void
    {
        $registration = CardRecurringRegistration::create('Subskrypcja')
            ->withFrequency(CardRecurringFrequency::MONTHLY)
            ->withLimitAmt(Money::pln(5999))
            ->withTotLimitAmt(Money::pln(71988))
            ->withLimitAmtFixed(true)
            ->withExpirationDate('2027-07-01');

        self::assertSame([
            'label' => 'Subskrypcja',
            'frequency' => 'MONTHLY',
            'limit_amt' => 5999,
            'tot_limit_amt' => 71988,
            'is_limit_amt_fixed' => true,
            'expiration_date' => '2027-07-01',
        ], $registration->toArray());
    }

    public function testRejectsBadFrequency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CardRecurringRegistration::create('X')->withFrequency('SOMETIMES');
    }

    public function testRejectsBadExpirationDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CardRecurringRegistration::create('X')->withExpirationDate('01-07-2027');
    }
}

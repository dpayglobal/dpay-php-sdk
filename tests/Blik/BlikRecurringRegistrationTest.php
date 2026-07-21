<?php

declare(strict_types=1);

namespace DPay\Tests\Blik;

use DPay\Blik\BlikRecurringRegistration;
use DPay\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BlikRecurringRegistrationTest extends TestCase
{
    public function testToArrayKeepsOrder(): void
    {
        $registration = BlikRecurringRegistration::create('Subskrypcja', 'A', '30D')
            ->withValue(Money::pln(2999))
            ->withLimitAmt(5999)
            ->withLimitAmtFixed(false)
            ->withInitDate('2026-08-01');

        self::assertSame([
            'label' => 'Subskrypcja',
            'type' => 'PAYID',
            'model' => 'A',
            'frequency' => '30D',
            'value' => '29.99',
            'limit_amt' => 5999,
            'is_limit_amt_fixed' => false,
            'init_date' => '2026-08-01',
        ], $registration->toArray());
    }

    public function testRejectsBadFrequency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BlikRecurringRegistration::create('X', 'A', '0D');
    }

    public function testRejectsBadModel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BlikRecurringRegistration::create('X', 'B', '30D');
    }

    public function testRejectsBadExpirationDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BlikRecurringRegistration::create('X', 'A', '30D')->withExpirationDate('01-08-2026');
    }

    public function testRejectsBadInitDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BlikRecurringRegistration::create('X', 'A', '30D')->withInitDate('2026/08/01');
    }
}

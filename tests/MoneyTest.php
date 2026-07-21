<?php

declare(strict_types=1);

namespace DPay\Tests;

use DPay\Currency;
use DPay\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testPlnFactory(): void
    {
        $money = Money::pln(1050);
        self::assertSame(1050, $money->getMinor());
        self::assertSame('PLN', $money->getCurrency());
        self::assertSame('10.50', $money->toDecimal());
    }

    public function testToDecimalPadsFraction(): void
    {
        self::assertSame('10.00', Money::pln(1000)->toDecimal());
        self::assertSame('0.05', Money::pln(5)->toDecimal());
        self::assertSame('1.00', Money::pln(100)->toDecimal());
    }

    public function testNegativeAmount(): void
    {
        $money = Money::pln(-2000);
        self::assertTrue($money->isNegative());
        self::assertSame('-20.00', $money->toDecimal());
    }

    public function testFromDecimal(): void
    {
        self::assertSame(1050, Money::fromDecimal('10.50', Currency::PLN)->getMinor());
        self::assertSame(1050, Money::fromDecimal('10.5', Currency::PLN)->getMinor());
        self::assertSame(1000, Money::fromDecimal('10', Currency::PLN)->getMinor());
        self::assertSame(-3000, Money::fromDecimal('-30.00', Currency::PLN)->getMinor());
    }

    public function testFromDecimalRejectsGarbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimal('10,50', Currency::PLN);
    }

    public function testFromDecimalRejectsTooManyPlaces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimal('10.505', Currency::PLN);
    }

    public function testFromApiNumber(): void
    {
        self::assertSame(3000, Money::fromApiNumber(30, Currency::PLN)->getMinor());
        self::assertSame(2999, Money::fromApiNumber(29.99, Currency::PLN)->getMinor());
        self::assertSame(2999, Money::fromApiNumber('29.99', Currency::PLN)->getMinor());
        self::assertSame(-1000, Money::fromApiNumber(-10.0, Currency::PLN)->getMinor());
    }

    public function testOfValidatesCurrency(): void
    {
        $money = Money::of(500, Currency::EUR);
        self::assertSame('EUR', $money->getCurrency());
        $this->expectException(InvalidArgumentException::class);
        Money::of(500, 'zl');
    }

    public function testEquals(): void
    {
        self::assertTrue(Money::pln(100)->equals(Money::pln(100)));
        self::assertFalse(Money::pln(100)->equals(Money::pln(101)));
        self::assertFalse(Money::pln(100)->equals(Money::of(100, Currency::EUR)));
    }

    public function testTryFromApiNumberAcceptsValidInput(): void
    {
        $fromInt = Money::tryFromApiNumber(30, Currency::PLN);
        $fromFloat = Money::tryFromApiNumber(29.99, Currency::PLN);
        $fromString = Money::tryFromApiNumber('29.99', Currency::PLN);

        self::assertNotNull($fromInt);
        self::assertNotNull($fromFloat);
        self::assertNotNull($fromString);
        self::assertSame(3000, $fromInt->getMinor());
        self::assertSame(2999, $fromFloat->getMinor());
        self::assertSame(2999, $fromString->getMinor());
    }

    public function testTryFromApiNumberReturnsNullForMalformedInput(): void
    {
        self::assertNull(Money::tryFromApiNumber('1e3', Currency::PLN));
        self::assertNull(Money::tryFromApiNumber('.5', Currency::PLN));
        self::assertNull(Money::tryFromApiNumber('10.', Currency::PLN));
        self::assertNull(Money::tryFromApiNumber('+5', Currency::PLN));
        self::assertNull(Money::tryFromApiNumber(' 10.5', Currency::PLN));
        self::assertNull(Money::tryFromApiNumber('N/A', Currency::PLN));
        self::assertNull(Money::tryFromApiNumber(null, Currency::PLN));
        self::assertNull(Money::tryFromApiNumber([], Currency::PLN));
        self::assertNull(Money::tryFromApiNumber(30, 'zl'));
    }
}

<?php

declare(strict_types=1);

namespace DPay\Tests;

use DPay\Currency;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    public function testConstants(): void
    {
        self::assertSame('PLN', Currency::PLN);
        self::assertSame('EUR', Currency::EUR);
        self::assertSame('CZK', Currency::CZK);
    }

    public function testAssertValidAcceptsIsoCode(): void
    {
        Currency::assertValid('GBP');
        $this->addToAssertionCount(1);
    }

    public function testAssertValidRejectsLowercase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Currency::assertValid('pln');
    }

    public function testIsValid(): void
    {
        self::assertTrue(Currency::isValid('PLN'));
        self::assertFalse(Currency::isValid('pln'));
        self::assertFalse(Currency::isValid('PLNX'));
        self::assertFalse(Currency::isValid(''));
    }
}

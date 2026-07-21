<?php

declare(strict_types=1);

namespace DPay\Tests\Payment;

use DPay\Payment\TransactionType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TransactionTypeTest extends TestCase
{
    public function testConstants(): void
    {
        self::assertSame('transfers', TransactionType::TRANSFERS);
        self::assertSame('card_recurring', TransactionType::CARD_RECURRING);
        self::assertCount(7, TransactionType::ALL);
    }

    public function testAssertValidAcceptsKnownValue(): void
    {
        TransactionType::assertValid('transfers');
        $this->addToAssertionCount(1);
    }

    public function testAssertValidRejectsUnknownValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TransactionType::assertValid('cash');
    }
}

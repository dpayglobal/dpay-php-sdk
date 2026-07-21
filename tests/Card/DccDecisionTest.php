<?php

declare(strict_types=1);

namespace DPay\Tests\Card;

use DPay\Card\DccDecision;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DccDecisionTest extends TestCase
{
    public function testConstants(): void
    {
        self::assertSame('accept', DccDecision::ACCEPT);
        self::assertSame('reject', DccDecision::REJECT);
    }

    public function testAssertValidRejectsUnknownValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DccDecision::assertValid('maybe');
    }
}

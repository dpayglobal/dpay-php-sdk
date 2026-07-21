<?php

declare(strict_types=1);

namespace DPay\Tests\Internal;

use DPay\Internal\ChecksumCalculator;
use PHPUnit\Framework\TestCase;

final class ChecksumCalculatorTest extends TestCase
{
    private ChecksumCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ChecksumCalculator('secret123');
    }

    public function testRegisterPaymentChecksum(): void
    {
        $checksum = $this->calculator->secretSecond('MyShop', [
            '10.00',
            'https://shop.example/ok',
            'https://shop.example/fail',
            'https://shop.example/ipn',
        ]);
        self::assertSame(
            'ae7fd11b457d6fc41edd193dfb0dd42b471bd6d312c1ae12dffeb931371c4cd2',
            $checksum
        );
    }

    public function testRegisterPaymentChecksumWithNormalizedValue(): void
    {
        $checksum = $this->calculator->secretSecond('MyShop', [
            '1.00',
            'https://shop.example/ok',
            'https://shop.example/fail',
            'https://shop.example/ipn',
        ]);
        self::assertSame(
            '5c97b7209b5d28edcb3768acbcb060030830c088d1f3335ede9dc9a9f949cd05',
            $checksum
        );
    }

    public function testBlikAliasChecksum(): void
    {
        $checksum = $this->calculator->secretSecond('MyShop', ['DPAY.UID.123456.abc12345']);
        self::assertSame(
            '231f56ec6cae2209e0743a34ad2a2e38c43dd78df63cafffe69a9ea2c4fb3490',
            $checksum
        );
    }

    public function testOrderedBodyFullRefund(): void
    {
        $checksum = $this->calculator->orderedBody([
            'MyShop',
            '30D9493D-1D73-3FBD-A5D4-633723CC7A68',
        ]);
        self::assertSame(
            '3b345f55900ae6a43220dd14a3827ed9a26a02a3eb46b856d302e2baa44b48a4',
            $checksum
        );
    }

    public function testOrderedBodyPartialRefundWithReason(): void
    {
        $checksum = $this->calculator->orderedBody([
            'MyShop',
            '30D9493D-1D73-3FBD-A5D4-633723CC7A68',
            '15.00',
            'reklamacja',
        ]);
        self::assertSame(
            '5c00684addf39fda89d3d6cff88abf26bb8173119056fd3012050422943c798a',
            $checksum
        );
    }

    public function testOrderedBodyIsOrderSensitive(): void
    {
        $ordered = $this->calculator->orderedBody(['MyShop', 'TX-1']);
        $reversed = $this->calculator->orderedBody(['TX-1', 'MyShop']);
        self::assertNotSame($ordered, $reversed);
    }

    public function testOrderedBodyCastsIntegers(): void
    {
        $checksum = $this->calculator->orderedBody(['MyShop', 1753100000]);
        self::assertSame(
            '189d0301f163fb105c9f3d607e8fe0adbbfc5ecd8e16271a682d672f43ab623c',
            $checksum
        );
    }

    public function testOrderedBodyWithdraw(): void
    {
        $checksum = $this->calculator->orderedBody(['MyShop', 12345]);
        self::assertSame(
            '9c7f5305d2de640ed3d1915f6ca33d6040b1583fab56676d0fcdcf65765b223d',
            $checksum
        );
    }
}

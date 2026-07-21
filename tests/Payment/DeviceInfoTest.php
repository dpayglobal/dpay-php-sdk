<?php

declare(strict_types=1);

namespace DPay\Tests\Payment;

use DPay\Payment\DeviceInfo;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DeviceInfoTest extends TestCase
{
    private function deviceInfo(): DeviceInfo
    {
        return DeviceInfo::create(
            'text/html,application/xhtml+xml',
            'pl-PL',
            24,
            1200,
            1920,
            -60,
            'Mozilla/5.0',
            'Win32',
            '10.10,10.10',
            'device-1',
            'Netscape'
        );
    }

    public function testToArrayUsesApiKeys(): void
    {
        $data = $this->deviceInfo()->toArray();

        self::assertSame('pl-PL', $data['browserLanguage']);
        self::assertSame(24, $data['browserColorDepth']);
        self::assertSame(-60, $data['browserTZ']);
        self::assertSame('device-1', $data['deviceID']);
        self::assertSame('Netscape', $data['applicationName']);
        self::assertArrayNotHasKey('browserJavaEnabled', $data);
    }

    public function testJavaEnabledSerializesAsString(): void
    {
        $data = $this->deviceInfo()->withBrowserJavaEnabled(false)->toArray();

        self::assertSame('false', $data['browserJavaEnabled']);
    }

    public function testDeviceIdMaxLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DeviceInfo::create('a', 'pl', 24, 1, 1, 0, 'ua', 'Win32', '0,0', str_repeat('x', 65), 'app');
    }
}

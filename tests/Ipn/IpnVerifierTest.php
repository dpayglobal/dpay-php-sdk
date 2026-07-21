<?php

declare(strict_types=1);

namespace DPay\Tests\Ipn;

use DPay\Exception\SignatureVerificationException;
use DPay\Ipn\IpnEvent;
use DPay\Ipn\IpnVerifier;
use PHPUnit\Framework\TestCase;

final class IpnVerifierTest extends TestCase
{
    private const TRANSFER_SIGNATURE = '02c605e72282ad633cdf8f514bd1666c67dfbf16ef202a60002b3145e1ea5707';

    private const DCB_SIGNATURE = '7cb0511964a4db9a604b75bfa742b9575ee0b2d188e47331d4b9fb1e2cf6b543';

    /**
     * @param array<string, mixed> $overrides
     */
    private function transferPayload(array $overrides = []): string
    {
        $payload = array_merge([
            'id' => '30D9493D-1D73-3FBD-A5D4-633723CC7A68',
            'amount' => '10.00',
            'email' => 'client@example.com',
            'type' => 'transfer',
            'attempt' => 1,
            'version' => 1,
            'custom' => 'order-1234',
            'signature' => self::TRANSFER_SIGNATURE,
        ], $overrides);

        return (string) json_encode($payload);
    }

    public function testValidTransfer(): void
    {
        $event = IpnVerifier::constructEvent($this->transferPayload(), 'secret123');

        self::assertSame('30D9493D-1D73-3FBD-A5D4-633723CC7A68', $event->getId());
        self::assertSame('10.00', $event->getAmount());
        self::assertSame('client@example.com', $event->getEmail());
        self::assertTrue($event->isTransfer());
        self::assertSame(1, $event->getAttempt());
        self::assertSame(1, $event->getVersion());
        self::assertSame('order-1234', $event->getCustom());
        self::assertNull($event->getCapturePaymentId());
    }

    public function testTamperedAmountThrows(): void
    {
        $this->expectException(SignatureVerificationException::class);
        IpnVerifier::constructEvent($this->transferPayload(['amount' => '9999.00']), 'secret123');
    }

    public function testWrongSecretThrows(): void
    {
        $this->expectException(SignatureVerificationException::class);
        IpnVerifier::constructEvent($this->transferPayload(), 'other-secret');
    }

    public function testDcbSignatureOmitsEmail(): void
    {
        $payload = (string) json_encode([
            'id' => '30D9493D-1D73-3FBD-A5D4-633723CC7A68',
            'amount' => '10.00',
            'type' => 'dcb',
            'attempt' => 1,
            'version' => 1,
            'custom' => 'order-1234',
            'signature' => self::DCB_SIGNATURE,
        ]);

        $event = IpnVerifier::constructEvent($payload, 'secret123');

        self::assertTrue($event->isDcb());
        self::assertNull($event->getEmail());
    }

    public function testCaptureExposesCapturePaymentId(): void
    {
        $raw = [
            'id' => '30D9493D-1D73-3FBD-A5D4-633723CC7A68',
            'amount' => '10.00',
            'email' => 'client@example.com',
            'type' => 'capture',
            'attempt' => 1,
            'version' => 1,
            'custom' => 'order-1234',
            'capture_payment_id' => 'CAP-1',
        ];
        $concat = $raw['id'] . 'secret123' . $raw['amount'] . $raw['email'] . 'capture'
            . $raw['attempt'] . $raw['version'] . $raw['custom'];
        $raw['signature'] = hash('sha256', $concat);

        $event = IpnVerifier::constructEvent((string) json_encode($raw), 'secret123');

        self::assertTrue($event->isCapture());
        self::assertSame('CAP-1', $event->getCapturePaymentId());
    }

    public function testInvalidJsonThrows(): void
    {
        $this->expectException(SignatureVerificationException::class);
        IpnVerifier::constructEvent('not-json', 'secret123');
    }

    public function testMissingSignatureThrows(): void
    {
        $payload = (string) json_encode(['id' => 'x', 'amount' => '1.00', 'type' => 'transfer', 'attempt' => 1, 'version' => 1]);

        $this->expectException(SignatureVerificationException::class);
        IpnVerifier::constructEvent($payload, 'secret123');
    }

    public function testAckConstant(): void
    {
        self::assertSame('OK', IpnEvent::ACK);
    }
}

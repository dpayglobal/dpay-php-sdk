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

    /**
     * @param array<string, mixed> $overrides
     */
    private function aliasUpdatePayload(array $overrides = []): string
    {
        $payload = array_merge([
            'id' => '30D9493D-1D73-3FBD-A5D4-633723CC7A68',
            'type' => 'alias_update',
            'change_type' => 'ALIAS_UNREGISTER',
            'alias_type' => 'UID',
            'alias_status' => 'UNREGISTERED',
            'alias_label' => 'Moj sklep',
            'alias_key' => 'KEY-1',
            'alias_expire_at' => null,
            'change_reason_category' => 'USER',
            'change_reason_message' => 'Unregistered in the banking app',
            'version' => 1,
        ], $overrides);
        $payload['signature'] ??= hash('sha256', '30D9493D-1D73-3FBD-A5D4-633723CC7A68' . 'secret123' . 'alias_update' . '1');

        return (string) json_encode($payload);
    }

    public function testValidAliasUpdate(): void
    {
        $event = IpnVerifier::constructEvent($this->aliasUpdatePayload(), 'secret123');

        self::assertTrue($event->isAliasUpdate());
        self::assertFalse($event->isTransfer());
        self::assertSame('', $event->getAmount());
        $alias = $event->getAliasUpdate();
        self::assertNotNull($alias);
        self::assertSame(\DPay\Ipn\AliasUpdate::CHANGE_UNREGISTER, $alias->getChangeType());
        self::assertSame(\DPay\Ipn\AliasUpdate::ALIAS_TYPE_UID, $alias->getAliasType());
        self::assertSame(\DPay\Ipn\AliasUpdate::STATUS_UNREGISTERED, $alias->getAliasStatus());
        self::assertFalse($alias->isActive());
        self::assertSame('KEY-1', $alias->getAliasKey());
        self::assertSame('Moj sklep', $alias->getAliasLabel());
        self::assertNull($alias->getAliasExpireAt());
        self::assertSame('USER', $alias->getChangeReasonCategory());
        self::assertSame('Unregistered in the banking app', $alias->getChangeReasonMessage());
    }

    public function testAliasUpdateSignatureIgnoresTheAliasFields(): void
    {
        // The signature covers only id, type and version - a changed status still verifies, a changed id does not
        $event = IpnVerifier::constructEvent($this->aliasUpdatePayload(['alias_status' => 'ACTIVE']), 'secret123');
        self::assertNotNull($event->getAliasUpdate());

        $this->expectException(SignatureVerificationException::class);
        IpnVerifier::constructEvent($this->aliasUpdatePayload([
            'id' => 'OTHER',
            'signature' => hash('sha256', '30D9493D-1D73-3FBD-A5D4-633723CC7A68secret123alias_update1'),
        ]), 'secret123');
    }

    public function testAliasUpdateWithoutVersionIsInvalid(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Invalid IPN payload');
        IpnVerifier::constructEvent($this->aliasUpdatePayload(['version' => null]), 'secret123');
    }

    public function testAliasUpdateIgnoresUnsignedTransferFields(): void
    {
        // amount, email, attempt and custom are not part of the alias_update signature - an injected value
        // must not reach handlers that read them without checking the type
        $event = IpnVerifier::constructEvent($this->aliasUpdatePayload([
            'amount' => '999.00',
            'email' => 'attacker@example.com',
            'attempt' => 7,
            'custom' => 'order-1234',
        ]), 'secret123');

        self::assertTrue($event->isAliasUpdate());
        self::assertSame('', $event->getAmount());
        self::assertNull($event->getEmail());
        self::assertSame(0, $event->getAttempt());
        self::assertNull($event->getCustom());
        self::assertSame('30D9493D-1D73-3FBD-A5D4-633723CC7A68', $event->getId());
        self::assertSame(1, $event->getVersion());
    }

    public function testTransferSignatureDoesNotVerifyAsAliasUpdate(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Invalid IPN signature');
        IpnVerifier::constructEvent($this->transferPayload(['type' => 'alias_update']), 'secret123');
    }

    public function testAliasUpdateSignatureDoesNotVerifyAsTransfer(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Invalid IPN signature');
        IpnVerifier::constructEvent($this->aliasUpdatePayload([
            'type' => 'transfer',
            'amount' => '',
            'attempt' => 1,
        ]), 'secret123');
    }

    public function testTransferHasNoAliasUpdate(): void
    {
        $event = IpnVerifier::constructEvent($this->transferPayload(), 'secret123');

        self::assertFalse($event->isAliasUpdate());
        self::assertNull($event->getAliasUpdate());
    }
}

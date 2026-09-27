<?php

declare(strict_types=1);

namespace DPay\Tests\Webhook;

use DPay\Exception\SignatureVerificationException;
use DPay\Tests\Support\ApiVectors;
use DPay\Webhook\WebhookVerifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class WebhookVerifierTest extends TestCase
{
    /**
     * @return array{secret: string, old_secret: string, id: string, timestamp: int, body: string, signature: string, rotation_signature: string}
     */
    private function vector(): array
    {
        return ApiVectors::webhook();
    }

    /**
     * @return array<string, string>
     */
    private function headers(?string $signature = null): array
    {
        $vector = $this->vector();

        return [
            'Webhook-Id' => $vector['id'],
            'WEBHOOK-TIMESTAMP' => (string) $vector['timestamp'],
            'webhook-signature' => $signature ?? $vector['signature'],
        ];
    }

    public function testVerifiesTheSignatureAndReturnsTheEvent(): void
    {
        $vector = $this->vector();

        $event = WebhookVerifier::constructEvent($vector['body'], $this->headers(), $vector['secret'], 300, $vector['timestamp'] + 10);

        self::assertSame($vector['id'], $event->getId());
        self::assertSame('payment.succeeded', $event->getType());
        self::assertSame('payment', $event->getObjectType());
        self::assertSame(1000, $event->getObject()['amount']);
    }

    public function testAcceptsHeadersAsArraysAndTheSecretWithoutPrefix(): void
    {
        $vector = $this->vector();
        $headers = array_map(static fn (string $value): array => [$value], $this->headers());

        WebhookVerifier::verify($vector['body'], $headers, substr($vector['secret'], 6), 300, $vector['timestamp']);
        $this->addToAssertionCount(1);
    }

    public function testAcceptsEitherSignatureDuringARotation(): void
    {
        $vector = $this->vector();

        // Tylko stary sekret u odbiorcy, a nagłówek niesie dwa podpisy
        WebhookVerifier::verify($vector['body'], $this->headers($vector['rotation_signature']), $vector['old_secret'], 300, $vector['timestamp']);
        // Oba sekrety u odbiorcy
        WebhookVerifier::verify($vector['body'], $this->headers(), [$vector['old_secret'], $vector['secret']], 300, $vector['timestamp']);
        $this->addToAssertionCount(2);
    }

    public function testRejectsATamperedBody(): void
    {
        $vector = $this->vector();

        $this->expectException(SignatureVerificationException::class);
        WebhookVerifier::verify(str_replace('1000', '100000', $vector['body']), $this->headers(), $vector['secret'], 300, $vector['timestamp']);
    }

    public function testRejectsAnOldTimestamp(): void
    {
        $vector = $this->vector();

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('tolerance');
        WebhookVerifier::verify($vector['body'], $this->headers(), $vector['secret'], 300, $vector['timestamp'] + 301);
    }

    public function testRejectsMissingHeaders(): void
    {
        $vector = $this->vector();
        $headers = $this->headers();
        unset($headers['webhook-signature']);

        $this->expectException(SignatureVerificationException::class);
        WebhookVerifier::verify($vector['body'], $headers, $vector['secret'], 300, $vector['timestamp']);
    }

    public function testIgnoresSignaturesOfOtherVersions(): void
    {
        $vector = $this->vector();
        $v2 = 'v2,' . substr($vector['signature'], 3);

        $this->expectException(SignatureVerificationException::class);
        WebhookVerifier::verify($vector['body'], $this->headers($v2), $vector['secret'], 300, $vector['timestamp']);
    }

    public function testRejectsASecretThatIsNotBase64(): void
    {
        $vector = $this->vector();

        $this->expectException(InvalidArgumentException::class);
        WebhookVerifier::verify($vector['body'], $this->headers(), 'whsec_***', 300, $vector['timestamp']);
    }
}

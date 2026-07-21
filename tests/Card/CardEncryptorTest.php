<?php

declare(strict_types=1);

namespace DPay\Tests\Card;

use DPay\Card\CardData;
use DPay\Card\CardEncryptor;
use DPay\Exception\CardEncryptionException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CardEncryptorTest extends TestCase
{
    public function testEncryptRoundTrip(): void
    {
        $keyPair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($keyPair);
        $details = openssl_pkey_get_details($keyPair);
        self::assertIsArray($details);
        $publicPem = $details['key'];
        self::assertIsString($publicPem);

        $encryptor = new CardEncryptor();
        $encrypted = $encryptor->encrypt(
            new CardData('4111 1111 1111 1111', '123', '12/30'),
            'TX-123',
            $publicPem
        );

        $cipher = base64_decode($encrypted, true);
        self::assertIsString($cipher);
        $decrypted = '';
        self::assertTrue(openssl_private_decrypt($cipher, $decrypted, $keyPair, OPENSSL_PKCS1_PADDING));
        self::assertIsString($decrypted);
        $payload = json_decode($decrypted, true);
        self::assertIsArray($payload);
        self::assertSame('4111111111111111', $payload['PN']);
        self::assertSame('123', $payload['SC']);
        self::assertSame('12/30', $payload['DT']);
        self::assertSame('TX-123', $payload['ID']);
        self::assertIsInt($payload['TX']);
    }

    public function testInvalidKeyThrows(): void
    {
        $this->expectException(CardEncryptionException::class);
        (new CardEncryptor())->encrypt(new CardData('4111111111111111', '123', '12/30'), 'TX-1', 'not-a-pem');
    }

    public function testCardDataValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CardData('4111', '123', '12/30');
    }

    public function testExpiryValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CardData('4111111111111111', '123', '13/30');
    }
}

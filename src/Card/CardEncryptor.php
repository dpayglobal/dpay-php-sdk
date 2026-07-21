<?php

declare(strict_types=1);

namespace DPay\Card;

use DPay\Exception\CardEncryptionException;

final class CardEncryptor
{
    public function encrypt(CardData $card, string $transactionId, string $publicKeyPem): string
    {
        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) {
            throw new CardEncryptionException('Invalid RSA public key');
        }

        $payload = json_encode([
            'PN' => $card->getPan(),
            'SC' => $card->getCvv(),
            'DT' => $card->getExpiry(),
            'ID' => $transactionId,
            'TX' => time(),
        ]);
        if ($payload === false) {
            throw new CardEncryptionException('Unable to encode card payload');
        }

        $encrypted = '';
        if (!openssl_public_encrypt($payload, $encrypted, $key, OPENSSL_PKCS1_PADDING) || !is_string($encrypted)) {
            throw new CardEncryptionException('Card data encryption failed');
        }

        return base64_encode($encrypted);
    }
}

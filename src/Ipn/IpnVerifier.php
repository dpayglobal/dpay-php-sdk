<?php

declare(strict_types=1);

namespace DPay\Ipn;

use DPay\Exception\SignatureVerificationException;

final class IpnVerifier
{
    public static function constructEvent(string $rawBody, string $secretHash): IpnEvent
    {
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new SignatureVerificationException('Invalid IPN payload');
        }

        foreach (['id', 'amount', 'type', 'attempt', 'version', 'signature'] as $field) {
            if (!isset($payload[$field])) {
                throw new SignatureVerificationException('Invalid IPN payload');
            }
        }

        $signature = $payload['signature'];
        if (!is_string($signature)) {
            throw new SignatureVerificationException('Invalid IPN payload');
        }

        $type = is_scalar($payload['type']) ? (string) $payload['type'] : '';
        $id = is_scalar($payload['id']) ? (string) $payload['id'] : '';
        $amount = is_scalar($payload['amount']) ? (string) $payload['amount'] : '';
        $email = isset($payload['email']) && is_scalar($payload['email']) ? (string) $payload['email'] : '';
        $attempt = is_numeric($payload['attempt']) ? (string) $payload['attempt'] : '0';
        $version = is_numeric($payload['version']) ? (string) $payload['version'] : '0';
        $custom = isset($payload['custom']) && is_scalar($payload['custom']) ? (string) $payload['custom'] : '';

        $parts = [$id, $secretHash, $amount];
        if ($type !== IpnType::DCB) {
            $parts[] = $email;
        }
        $parts[] = $type;
        $parts[] = $attempt;
        $parts[] = $version;
        $parts[] = $custom;

        $expected = hash('sha256', implode('', $parts));
        if (!hash_equals($expected, $signature)) {
            throw new SignatureVerificationException('Invalid IPN signature');
        }

        return IpnEvent::fromVerifiedPayload($payload);
    }

    private function __construct()
    {
    }
}

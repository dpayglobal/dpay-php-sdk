<?php

declare(strict_types=1);

namespace DPay\Ipn;

use DPay\Exception\SignatureVerificationException;

/**
 * Verifies IPN notifications (JSON POST, acknowledged with the body `OK`):
 *  - transfer, dcb: sha256(id . SecretHash . amount . email . type . attempt . version . custom), dcb without email;
 *  - alias_update: sha256(id . SecretHash . type . version).
 */
final class IpnVerifier
{
    public static function constructEvent(string $rawBody, string $secretHash): IpnEvent
    {
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new SignatureVerificationException('Invalid IPN payload');
        }

        $type = isset($payload['type']) && is_scalar($payload['type']) ? (string) $payload['type'] : '';
        $required = $type === IpnType::ALIAS_UPDATE
            ? ['id', 'type', 'version', 'signature']
            : ['id', 'amount', 'type', 'attempt', 'version', 'signature'];
        foreach ($required as $field) {
            if (!isset($payload[$field])) {
                throw new SignatureVerificationException('Invalid IPN payload');
            }
        }

        $signature = $payload['signature'];
        if (!is_string($signature)) {
            throw new SignatureVerificationException('Invalid IPN payload');
        }

        $id = is_scalar($payload['id']) ? (string) $payload['id'] : '';
        $version = is_numeric($payload['version']) ? (string) $payload['version'] : '0';

        if ($type === IpnType::ALIAS_UPDATE) {
            $parts = [$id, $secretHash, $type, $version];
        } else {
            $amount = is_scalar($payload['amount']) ? (string) $payload['amount'] : '';
            $email = isset($payload['email']) && is_scalar($payload['email']) ? (string) $payload['email'] : '';
            $attempt = is_numeric($payload['attempt']) ? (string) $payload['attempt'] : '0';
            $custom = isset($payload['custom']) && is_scalar($payload['custom']) ? (string) $payload['custom'] : '';

            $parts = [$id, $secretHash, $amount];
            if ($type !== IpnType::DCB) {
                $parts[] = $email;
            }
            $parts[] = $type;
            $parts[] = $attempt;
            $parts[] = $version;
            $parts[] = $custom;
        }

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

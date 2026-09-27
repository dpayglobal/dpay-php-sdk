<?php

declare(strict_types=1);

namespace DPay\Webhook;

use DPay\Exception\SignatureVerificationException;
use InvalidArgumentException;

/**
 * Verifies dpay webhooks (Standard Webhooks): `webhook-signature` = `v1,` + base64(HMAC-SHA256(key, id.timestamp.body)),
 * where the key is the base64-decoded secret without the `whsec_` prefix. During secret rotation dpay sends two
 * signatures separated by a space - one match is enough.
 */
final class WebhookVerifier
{
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Verifies the signature and returns the event. Pass the raw request body exactly as received.
     *
     * @param array<string, string|array<int, string>> $headers request headers, any letter case
     * @param string|array<int, string> $secrets `whsec_...` secret, or several during a rotation
     */
    public static function constructEvent(
        string $rawBody,
        array $headers,
        $secrets,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE,
        ?int $now = null
    ): WebhookEvent {
        self::verify($rawBody, $headers, $secrets, $toleranceSeconds, $now);

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new SignatureVerificationException('Invalid webhook payload');
        }

        return WebhookEvent::fromArray($payload);
    }

    /**
     * @param array<string, string|array<int, string>> $headers
     * @param string|array<int, string> $secrets
     */
    public static function verify(
        string $rawBody,
        array $headers,
        $secrets,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE,
        ?int $now = null
    ): void {
        $id = self::header($headers, 'webhook-id');
        $timestamp = self::header($headers, 'webhook-timestamp');
        $signatureHeader = self::header($headers, 'webhook-signature');
        if ($id === null || $timestamp === null || $signatureHeader === null) {
            throw new SignatureVerificationException('Missing webhook-id, webhook-timestamp or webhook-signature header');
        }
        if (preg_match('/^\d+$/', $timestamp) !== 1) {
            throw new SignatureVerificationException('Invalid webhook-timestamp header');
        }
        if (abs(($now ?? time()) - (int) $timestamp) > $toleranceSeconds) {
            throw new SignatureVerificationException('Webhook timestamp is outside the tolerance zone');
        }

        $signed = $id . '.' . $timestamp . '.' . $rawBody;
        $expected = [];
        foreach (is_array($secrets) ? $secrets : [$secrets] as $secret) {
            $expected[] = base64_encode(hash_hmac('sha256', $signed, self::key($secret), true));
        }

        foreach (preg_split('/\s+/', trim($signatureHeader)) ?: [] as $entry) {
            $parts = explode(',', $entry, 2);
            if (count($parts) !== 2 || $parts[0] !== 'v1') {
                continue;
            }
            foreach ($expected as $candidate) {
                if (hash_equals($candidate, $parts[1])) {
                    return;
                }
            }
        }

        throw new SignatureVerificationException('No valid webhook signature found');
    }

    private static function key(string $secret): string
    {
        $encoded = strncmp($secret, 'whsec_', 6) === 0 ? substr($secret, 6) : $secret;
        $key = base64_decode($encoded, true);
        if ($key === false || $key === '') {
            throw new InvalidArgumentException('Webhook secret must be the whsec_ value from the dpay panel');
        }

        return $key;
    }

    /**
     * @param array<string, string|array<int, string>> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) !== 0) {
                continue;
            }
            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            return is_string($value) && $value !== '' ? $value : null;
        }

        return null;
    }

    private function __construct()
    {
    }
}

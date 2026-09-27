<?php

declare(strict_types=1);

namespace DPay\Internal;

final class ChecksumCalculator
{
    private string $secretHash;

    public function __construct(string $secretHash)
    {
        $this->secretHash = $secretHash;
    }

    /**
     * sha256(service|secretHash|field1|field2|...) - payment registration, BLIK aliases, recurring payments, events.
     *
     * @param array<int, int|float|string> $fields
     */
    public function secretSecond(string $service, array $fields): string
    {
        $parts = array_merge([$service, $this->secretHash], array_map('strval', $fields));

        return hash('sha256', implode('|', $parts));
    }

    /**
     * sha256(value1|value2|...|secretHash) over the request body in the order it is sent (PBL API: refunds,
     * transaction details, banks, payouts). The `checksum` key is skipped, nested objects (e.g. `webhook`)
     * contribute their leaf values in order, `null` and `false` give an empty segment and `true` gives `1`,
     * the same way the API casts JSON values to strings.
     *
     * @param array<mixed> $body
     */
    public function orderedBody(array $body): string
    {
        $parts = [];
        foreach ($body as $key => $value) {
            if ($key === 'checksum') {
                continue;
            }
            if (is_array($value)) {
                array_walk_recursive($value, static function ($leaf) use (&$parts): void {
                    $parts[] = self::segment($leaf);
                });
                continue;
            }
            $parts[] = self::segment($value);
        }

        return hash('sha256', implode('|', $parts) . '|' . $this->secretHash);
    }

    /**
     * sha256(operation|service|transactionId|amount|secretHash) - Cards API capture and cancellation. The operation
     * name keeps a capture checksum from authorising a cancellation; without an amount the segment stays empty.
     */
    public function operation(string $operation, string $service, string $transactionId, ?string $amount): string
    {
        return hash('sha256', implode('|', [$operation, $service, $transactionId, $amount ?? '', $this->secretHash]));
    }

    /**
     * @param mixed $value
     */
    private static function segment($value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        if ($value === true) {
            return '1';
        }

        return is_scalar($value) ? (string) $value : '';
    }
}

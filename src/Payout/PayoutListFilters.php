<?php

declare(strict_types=1);

namespace DPay\Payout;

use DPay\Internal\ServerTrim;
use InvalidArgumentException;

/**
 * Validates the filters of PayoutService::list() - any array, as callers without static types may pass anything -
 * and returns them as body fields in the order the checksum signs them. Wrong keys, types and values throw
 * InvalidArgumentException like the other argument checks (never TypeError).
 *
 * @internal
 */
final class PayoutListFilters
{
    private const KEYS = ['date_from', 'date_to', 'state', 'type'];

    /**
     * @param array<mixed> $filters
     * @return array<string, string|int> `date_from`, `date_to` (trimmed like the API trims them), `state`, `type`
     */
    public static function toBody(array $filters): array
    {
        $unknown = array_diff(array_keys($filters), self::KEYS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('Unknown payout filter "%s"', implode('", "', $unknown)));
        }

        $body = [];
        foreach (['date_from', 'date_to'] as $dateField) {
            $date = $filters[$dateField] ?? null;
            if ($date !== null) {
                if (!is_string($date)) {
                    throw new InvalidArgumentException(sprintf('%s must be a string', $dateField));
                }
                // The API trims strings before it verifies the checksum - an untrimmed value would fail with 401
                $body[$dateField] = ServerTrim::required($date, $dateField);
            }
        }
        $state = $filters['state'] ?? null;
        if ($state !== null) {
            if (!is_int($state)) {
                throw new InvalidArgumentException('state must be an integer');
            }
            PayoutState::assertValid($state);
            $body['state'] = $state;
        }
        $type = $filters['type'] ?? null;
        if ($type !== null) {
            if (!is_string($type)) {
                throw new InvalidArgumentException('type must be a string');
            }
            PayoutType::assertValid($type);
            $body['type'] = $type;
        }

        return $body;
    }

    private function __construct()
    {
    }
}

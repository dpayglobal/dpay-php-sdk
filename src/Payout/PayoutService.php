<?php

declare(strict_types=1);

namespace DPay\Payout;

use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;
use Generator;
use InvalidArgumentException;

/**
 * Payouts (withdrawals) of your account. Every endpoint signs the body in the order it is sent:
 * sha256(value1|value2|...|SecretHash) - optional fields are sent only when set.
 */
final class PayoutService
{
    private const LIST_FILTERS = ['date_from', 'date_to', 'state', 'type'];

    private ApiRequestor $api;

    public function __construct(ApiRequestor $api)
    {
        $this->api = $api;
    }

    public function details(int $withdrawId, ?int $timestamp = null): PayoutDetails
    {
        $body = ['service' => $this->api->config()->service()];
        if ($timestamp !== null) {
            $body['timestamp'] = $timestamp;
        }
        $body['withdraw_id'] = $withdrawId;
        $body['checksum'] = $this->api->checksum()->orderedBody(array_values($body));

        $data = $this->api->postJson(BaseUrls::PANEL, '/api/v1/pbl/withdraws/details', $body);

        return PayoutDetails::fromArray($data);
    }

    /**
     * Your payouts, newest first - the source of `withdraw_id`.
     *
     * @param array{date_from?: string, date_to?: string, state?: int, type?: string} $filters creation date range
     *        (any date or date-time, e.g. `2026-07-01`, `2026-07-31 23:59:59`), PayoutState, PayoutType
     * @param int|null $perPage 1-200 (API default 50)
     */
    public function list(array $filters = [], ?int $page = null, ?int $perPage = null): PayoutPage
    {
        $body = ['service' => $this->api->config()->service()];
        $unknown = array_diff(array_keys($filters), self::LIST_FILTERS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('Unknown payout filter "%s"', implode('", "', $unknown)));
        }
        foreach (['date_from', 'date_to'] as $dateField) {
            if (isset($filters[$dateField])) {
                if (trim($filters[$dateField]) === '') {
                    throw new InvalidArgumentException(sprintf('%s must not be empty', $dateField));
                }
                $body[$dateField] = $filters[$dateField];
            }
        }
        if (isset($filters['state'])) {
            PayoutState::assertValid($filters['state']);
            $body['state'] = $filters['state'];
        }
        if (isset($filters['type'])) {
            PayoutType::assertValid($filters['type']);
            $body['type'] = $filters['type'];
        }
        $this->addPage($body, $page, $perPage, 200);
        $body['checksum'] = $this->api->checksum()->orderedBody($body);

        return PayoutPage::fromArray($this->api->postJson(BaseUrls::PANEL, '/api/v1/pbl/withdraws', $body));
    }

    /**
     * Iterates over all matching payouts page by page (newest first).
     *
     * @param array{date_from?: string, date_to?: string, state?: int, type?: string} $filters
     * @return Generator<int, PayoutListItem>
     */
    public function iterate(array $filters = [], ?int $perPage = null): Generator
    {
        $page = 1;
        do {
            $result = $this->list($filters, $page, $perPage);
            foreach ($result->getItems() as $item) {
                yield $item;
            }
            ++$page;
        } while ($result->hasMore());
    }

    /**
     * The payout with the payments it settles: header, totals of the whole settlement and one page of positions.
     *
     * @param int|null $perPage 1-500 (API default 100)
     */
    public function detailsFull(int $withdrawId, ?int $page = null, ?int $perPage = null): PayoutSettlement
    {
        $body = [
            'service' => $this->api->config()->service(),
            'withdraw_id' => $withdrawId,
        ];
        $this->addPage($body, $page, $perPage, 500);
        $body['checksum'] = $this->api->checksum()->orderedBody($body);

        return PayoutSettlement::fromArray($this->api->postJson(BaseUrls::PANEL, '/api/v1/pbl/withdraws/details/full', $body));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function addPage(array &$body, ?int $page, ?int $perPage, int $maxPerPage): void
    {
        if ($page !== null) {
            if ($page < 1) {
                throw new InvalidArgumentException('page must be at least 1');
            }
            $body['page'] = $page;
        }
        if ($perPage !== null) {
            if ($perPage < 1 || $perPage > $maxPerPage) {
                throw new InvalidArgumentException(sprintf('per_page must be between 1 and %d', $maxPerPage));
            }
            $body['per_page'] = $perPage;
        }
    }
}

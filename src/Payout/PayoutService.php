<?php

declare(strict_types=1);

namespace DPay\Payout;

use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;
use Generator;
use InvalidArgumentException;

/**
 * Payouts (withdrawals) of your account. Every endpoint signs the body in the order it is sent:
 * sha256(value1|value2|...|SecretHash) - optional fields are sent only when set, strings trimmed like the API trims
 * them (ServerTrim).
 */
final class PayoutService
{
    private ApiRequestor $api;

    public function __construct(ApiRequestor $api)
    {
        $this->api = $api;
    }

    public function details(int $withdrawId, ?int $timestamp = null): PayoutDetails
    {
        $body = ['service' => $this->api->panelService()];
        if ($timestamp !== null) {
            $body['timestamp'] = $timestamp;
        }
        $body['withdraw_id'] = $withdrawId;
        $body = $this->api->signPanelBody($body);

        $data = $this->api->postJson(BaseUrls::PANEL, '/api/v1/pbl/withdraws/details', $body);

        return PayoutDetails::fromArray($data);
    }

    /**
     * Your payouts, newest first - the source of `withdraw_id`.
     *
     * @param array{date_from?: string, date_to?: string, state?: int, type?: string} $filters creation date range
     *        (strings: any date or date-time, e.g. `2026-07-01`, `2026-07-31 23:59:59`; sent trimmed like every
     *        string of a panel request), PayoutState (int), PayoutType (string) - a value of another type throws
     *        InvalidArgumentException
     * @param int|null $perPage 1-200 (API default 50)
     */
    public function list(array $filters = [], ?int $page = null, ?int $perPage = null): PayoutPage
    {
        $body = ['service' => $this->api->panelService()];
        $body += PayoutListFilters::toBody($filters);
        $this->addPage($body, $page, $perPage, 200);
        $body = $this->api->signPanelBody($body);

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
            'service' => $this->api->panelService(),
            'withdraw_id' => $withdrawId,
        ];
        $this->addPage($body, $page, $perPage, 500);
        $body = $this->api->signPanelBody($body);

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

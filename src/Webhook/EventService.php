<?php

declare(strict_types=1);

namespace DPay\Webhook;

use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;
use Generator;
use InvalidArgumentException;

/**
 * Events API: the event history of the service (the same envelopes as webhooks), newest first. Use it to catch up
 * after an outage of your webhook endpoint.
 */
final class EventService
{
    private ApiRequestor $api;

    public function __construct(ApiRequestor $api)
    {
        $this->api = $api;
    }

    /**
     * @param array{types?: array<int, string>, created_from?: string, created_to?: string, starting_after?: string, limit?: int} $params
     * @param int|null $timestamp Unix time for the checksum (defaults to now; the API accepts +/- 300 s)
     */
    public function list(array $params = [], ?int $timestamp = null): EventPage
    {
        $service = $this->api->config()->service();
        $timestamp ??= time();

        $body = ['service' => $service, 'timestamp' => $timestamp];
        if (isset($params['types'])) {
            if ($params['types'] === [] || count($params['types']) !== count(array_unique($params['types']))) {
                throw new InvalidArgumentException('Event types must be a non-empty list of distinct types');
            }
            WebhookEventType::assertAllowed($params['types'], WebhookEventType::MERCHANT, 'the Events API');
            $body['types'] = array_values($params['types']);
        }
        foreach (['created_from', 'created_to'] as $dateField) {
            if (isset($params[$dateField])) {
                $body[$dateField] = $params[$dateField];
            }
        }
        if (isset($params['starting_after'])) {
            if (preg_match('/^evt_[0-9a-z]{26}$/', $params['starting_after']) !== 1) {
                throw new InvalidArgumentException('starting_after must be an event id (evt_...)');
            }
            $body['starting_after'] = $params['starting_after'];
        }
        if (isset($params['limit'])) {
            if ($params['limit'] < 1 || $params['limit'] > 100) {
                throw new InvalidArgumentException('limit must be between 1 and 100');
            }
            $body['limit'] = $params['limit'];
        }
        $body['checksum'] = $this->api->checksum()->secretSecond($service, [(string) $timestamp]);

        return EventPage::fromArray($this->api->postJson(BaseUrls::API_PAYMENTS, '/api/v1_0/events', $body));
    }

    /**
     * Iterates over all matching events page by page (newest first).
     *
     * @param array{types?: array<int, string>, created_from?: string, created_to?: string, starting_after?: string, limit?: int} $params
     * @return Generator<int, WebhookEvent>
     */
    public function iterate(array $params = []): Generator
    {
        do {
            $page = $this->list($params);
            foreach ($page->getData() as $event) {
                yield $event;
            }
            $params['starting_after'] = $page->getNextStartingAfter();
        } while ($page->hasMore() && $params['starting_after'] !== null);
    }
}

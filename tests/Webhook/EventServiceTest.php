<?php

declare(strict_types=1);

namespace DPay\Tests\Webhook;

use DPay\Config;
use DPay\Internal\ApiRequestor;
use DPay\Tests\Support\MockHttpClient;
use DPay\Webhook\EventService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventServiceTest extends TestCase
{
    private MockHttpClient $http;

    private EventService $service;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $config = Config::fromArray(['service' => 'sdk-test-service', 'secret_hash' => 'sdk-test-hash-0001']);
        $this->service = new EventService(new ApiRequestor($config, $this->http));
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $id, string $type = 'payment.succeeded'): array
    {
        return ['id' => $id, 'type' => $type, 'api_version' => '2026-10-01', 'created' => '2026-09-27T10:05:00Z',
            'livemode' => true, 'service' => 'sdk-test-service', 'data' => ['object' => ['object' => 'payment', 'id' => 'TX-1']]];
    }

    public function testListSignsTheTimestampAndSendsFilters(): void
    {
        $this->http->queueJson(200, ['status' => 'success', 'data' => [$this->event('evt_01k6a8q2m4pz7h8c3v5n9t2x6y')], 'has_more' => false, 'next_starting_after' => null]);

        $page = $this->service->list(['types' => ['payment.succeeded', 'refund.failed'], 'limit' => 50], 1790503500);

        self::assertSame('https://api-payments.dpay.pl/api/v1_0/events', $this->http->lastRequest()->getUrl());
        self::assertSame([
            'service' => 'sdk-test-service',
            'timestamp' => 1790503500,
            'types' => ['payment.succeeded', 'refund.failed'],
            'limit' => 50,
            // sha256(service|hash|timestamp) - filtry poza sumą
            'checksum' => '390cb30baacbc92bf2244d049f4b500937c6209446dd2fd79829d7975321abf0',
        ], $this->http->lastRequestBody());
        self::assertCount(1, $page->getData());
        self::assertSame('payment.succeeded', $page->getData()[0]->getType());
        self::assertFalse($page->hasMore());
    }

    public function testIteratePagesUntilTheEnd(): void
    {
        $this->http->queueJson(200, ['status' => 'success', 'data' => [$this->event('evt_01k6a8q2m4pz7h8c3v5n9t2x6y')], 'has_more' => true, 'next_starting_after' => 'evt_01k6a8q2m4pz7h8c3v5n9t2x6y']);
        $this->http->queueJson(200, ['status' => 'success', 'data' => [$this->event('evt_01k6a8q2m4pz7h8c3v5n9t2x6a')], 'has_more' => false, 'next_starting_after' => 'evt_01k6a8q2m4pz7h8c3v5n9t2x6a']);

        $ids = [];
        foreach ($this->service->iterate(['limit' => 1]) as $event) {
            $ids[] = $event->getId();
        }

        self::assertSame(['evt_01k6a8q2m4pz7h8c3v5n9t2x6y', 'evt_01k6a8q2m4pz7h8c3v5n9t2x6a'], $ids);
        self::assertSame('evt_01k6a8q2m4pz7h8c3v5n9t2x6y', $this->http->lastRequestBody()['starting_after']);
    }

    public function testRejectsUnknownTypesBeforeCallingTheApi(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->list(['types' => ['merchant.updated']]);
    }
}

<?php

declare(strict_types=1);

namespace DPay\Tests\Refund;

use DPay\Config;
use DPay\Internal\ApiRequestor;
use DPay\Money;
use DPay\Refund\RefundService;
use DPay\Tests\Support\MockHttpClient;
use DPay\Webhook\WebhookTarget;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RefundWebhookTest extends TestCase
{
    private MockHttpClient $http;

    private RefundService $service;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $config = Config::fromArray(['service' => 'sdk-test-service', 'secret_hash' => 'sdk-test-hash-0001']);
        $this->service = new RefundService(new ApiRequestor($config, $this->http));
    }

    public function testWebhookValuesAreHashedInTheOrderSent(): void
    {
        $this->http->queueJson(200, ['status' => 'success', 'refund' => true, 'message' => 'dpay.pl A75AEBB4-4B89-4834-AD43-EF442C133769']);

        $this->service->create(
            'A75AEBB4-4B89-4834-AD43-EF442C133769',
            Money::pln(1500),
            'Zwrot',
            WebhookTarget::create('https://shop.example/webhooks/refunds', ['refund.succeeded', 'refund.failed'])
        );

        $body = $this->http->lastRequestBody();
        self::assertSame(['service', 'transaction_id', 'value', 'reason', 'webhook', 'checksum'], array_keys($body));
        self::assertSame('15.00', $body['value']);
        // ...|15.00|Zwrot|https://shop.example/webhooks/refunds|refund.succeeded|refund.failed|hash
        self::assertSame('53620f0ea6b46723866a46f2f0059c79cbe080e59d4f134508546be3fa08dacf', $body['checksum']);
    }

    public function testRefundWebhookOnlyAcceptsRefundEvents(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->create('TX', null, null, WebhookTarget::create('https://shop.example/webhooks', ['payment.succeeded']));
    }
}

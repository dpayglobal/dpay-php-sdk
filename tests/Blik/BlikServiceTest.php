<?php

declare(strict_types=1);

namespace DPay\Tests\Blik;

use DPay\Blik\BlikService;
use DPay\Config;
use DPay\Internal\ApiRequestor;
use DPay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

final class BlikServiceTest extends TestCase
{
    private MockHttpClient $http;

    private BlikService $service;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $config = Config::fromArray(['service' => 'MyShop', 'secret_hash' => 'secret123']);
        $this->service = new BlikService(new ApiRequestor($config, $this->http));
    }

    public function testAliasDetails(): void
    {
        $this->http->queueJson(200, [
            'status' => 'success',
            'data' => [
                'alias_value' => 'DPAY.UID.123456.abc12345',
                'alias_type' => 'UID',
                'status' => 'ACTIVE',
                'expiration_date' => null,
                'apps' => [['key' => 'app1', 'label' => 'My banking app']],
            ],
        ]);

        $alias = $this->service->alias('DPAY.UID.123456.abc12345');

        self::assertSame('DPAY.UID.123456.abc12345', $alias->getAliasValue());
        self::assertTrue($alias->isActive());
        self::assertCount(1, $alias->getApps());
        self::assertSame('My banking app', $alias->getApps()[0]->getLabel());

        $request = $this->http->lastRequest();
        self::assertSame('https://api-payments.dpay.pl/api/v1_0/payments/blik/aliases', $request->getUrl());
        $body = $this->http->lastRequestBody();
        self::assertSame(['service', 'alias_value', 'alias_type', 'checksum'], array_keys($body));
        self::assertSame(
            '231f56ec6cae2209e0743a34ad2a2e38c43dd78df63cafffe69a9ea2c4fb3490',
            $body['checksum']
        );
    }

    public function testUnregisterSendsReason(): void
    {
        $this->http->queueJson(200, ['status' => 'success', 'data' => ['status' => 'success']]);

        $this->service->unregisterAlias('DPAY.UID.123456.abc12345', 'UID', 'customer request');

        $body = $this->http->lastRequestBody();
        self::assertSame(['service', 'alias_value', 'alias_type', 'reason', 'checksum'], array_keys($body));
        self::assertSame('customer request', $body['reason']);
    }

    public function testRecurringStatus(): void
    {
        $this->http->queueJson(200, [
            'status' => 'success',
            'data' => [
                'alias_value' => 'PAYID-1',
                'alias_type' => 'PAYID',
                'status' => 'ACTIVE',
                'expiration_date' => null,
                'registration' => [
                    'model' => 'A',
                    'frequency' => '30D',
                    'limit_amt' => 5999,
                    'tot_limit_amt' => null,
                    'is_limit_amt_fixed' => false,
                    'init_date' => '2026-07-01',
                    'label' => 'Subskrypcja',
                    'registered_at' => '2026-07-01T10:00:00+02:00',
                ],
            ],
        ]);

        $status = $this->service->recurringStatus('PAYID-1');

        self::assertTrue($status->isActive());
        $registration = $status->getRegistration();
        self::assertNotNull($registration);
        self::assertSame('A', $registration->getModel());
        self::assertSame('30D', $registration->getFrequency());
        self::assertSame(5999, $registration->getLimitAmt());
    }

    public function testRecurringStatusWithoutRegistration(): void
    {
        $this->http->queueJson(200, [
            'status' => 'success',
            'data' => ['alias_value' => 'PAYID-1', 'alias_type' => 'PAYID', 'status' => null, 'registration' => null],
        ]);

        $status = $this->service->recurringStatus('PAYID-1');

        self::assertFalse($status->isActive());
        self::assertNull($status->getRegistration());
    }
}

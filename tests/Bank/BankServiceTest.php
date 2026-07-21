<?php

declare(strict_types=1);

namespace DPay\Tests\Bank;

use DPay\Bank\BankService;
use DPay\Config;
use DPay\Internal\ApiRequestor;
use DPay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

final class BankServiceTest extends TestCase
{
    private MockHttpClient $http;

    private BankService $service;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $config = Config::fromArray(['service' => 'MyShop', 'secret_hash' => 'secret123']);
        $this->service = new BankService(new ApiRequestor($config, $this->http));
    }

    public function testAll(): void
    {
        $this->http->queueJson(200, [
            ['id' => '24900005', 'name' => 'Alior Bank', 'image' => '24900005.png', 'on_from' => 0, 'on_to' => 24, 'test' => 0],
            ['id' => '10500002', 'name' => 'ING', 'image' => '10500002.png', 'test' => 1],
        ]);

        $banks = $this->service->all();

        self::assertCount(2, $banks);
        self::assertSame('24900005', $banks[0]->getId());
        self::assertSame('Alior Bank', $banks[0]->getName());
        self::assertFalse($banks[0]->isTest());
        self::assertTrue($banks[1]->isTest());
        self::assertSame('GET', $this->http->lastRequest()->getMethod());
        self::assertSame('https://panel.dpay.pl/api/v1/pbl/banks', $this->http->lastRequest()->getUrl());
    }

    public function testForServiceSignsBody(): void
    {
        $this->http->queueJson(200, [
            ['id' => '24900005', 'name' => 'Alior Bank', 'type' => 'paybylink'],
        ]);

        $banks = $this->service->forService(1753100000);

        self::assertCount(1, $banks);
        self::assertSame('paybylink', $banks[0]->getType());
        $body = json_decode((string) $this->http->lastRequest()->getBody(), true);
        self::assertSame(['service', 'timestamp', 'checksum'], array_keys($body));
        self::assertSame(1753100000, $body['timestamp']);
        self::assertSame(
            '189d0301f163fb105c9f3d607e8fe0adbbfc5ecd8e16271a682d672f43ab623c',
            $body['checksum']
        );
    }

    public function testForServiceDefaultsToCurrentTime(): void
    {
        $this->http->queueJson(200, []);

        $this->service->forService();

        $body = json_decode((string) $this->http->lastRequest()->getBody(), true);
        self::assertEqualsWithDelta(time(), $body['timestamp'], 5);
    }
}

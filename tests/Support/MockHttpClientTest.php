<?php

declare(strict_types=1);

namespace DPay\Tests\Support;

use DPay\Http\ApiRequest;
use LogicException;
use PHPUnit\Framework\TestCase;

final class MockHttpClientTest extends TestCase
{
    public function testQueueAndRecord(): void
    {
        $client = new MockHttpClient();
        $client->queueJson(200, ['ok' => true]);
        $request = new ApiRequest('POST', 'https://api.example/x', [], '{}');

        $response = $client->request($request);

        self::assertSame(200, $response->getStatus());
        self::assertSame(['ok' => true], $response->decodeJson());
        self::assertSame($request, $client->lastRequest());
        self::assertCount(1, $client->requests());
    }

    public function testThrowsOnEmptyQueue(): void
    {
        $client = new MockHttpClient();
        $this->expectException(LogicException::class);
        $client->request(new ApiRequest('GET', 'https://api.example/x'));
    }
}

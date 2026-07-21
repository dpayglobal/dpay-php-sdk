<?php

declare(strict_types=1);

namespace DPay\Tests\Internal;

use DPay\Config;
use DPay\Exception\ApiServerException;
use DPay\Exception\InvalidRequestException;
use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;
use DPay\Tests\Support\MockHttpClient;
use DPay\Version;
use PHPUnit\Framework\TestCase;

final class ApiRequestorTest extends TestCase
{
    private MockHttpClient $http;

    private ApiRequestor $api;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $config = Config::fromArray(['service' => 'MyShop', 'secret_hash' => 'secret123']);
        $this->api = new ApiRequestor($config, $this->http);
    }

    public function testPostJsonBuildsRequest(): void
    {
        $this->http->queueJson(200, ['status' => 'success']);

        $data = $this->api->postJson(BaseUrls::PANEL, '/api/v1/pbl/details', ['service' => 'MyShop']);

        self::assertSame(['status' => 'success'], $data);
        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://panel.dpay.pl/api/v1/pbl/details', $request->getUrl());
        self::assertSame('{"service":"MyShop"}', $request->getBody());
        $headers = $request->getHeaders();
        self::assertSame('application/json', $headers['Content-Type']);
        self::assertSame('application/json', $headers['Accept']);
        self::assertSame('dpay-php-sdk/' . Version::SDK . ' php/' . PHP_VERSION, $headers['User-Agent']);
    }

    public function testPostJsonPreservesBodyOrder(): void
    {
        $this->http->queueJson(200, ['status' => 'success']);

        $this->api->postJson(BaseUrls::PANEL, '/x', ['b' => '2', 'a' => '1']);

        self::assertSame('{"b":"2","a":"1"}', $this->http->lastRequest()->getBody());
    }

    public function testEmptyBodyEncodesAsObject(): void
    {
        $this->http->queueJson(200, ['status' => 'success']);

        $this->api->postJson(BaseUrls::API_PAYMENTS, '/x', []);

        self::assertSame('{}', $this->http->lastRequest()->getBody());
    }

    public function testThrowsMappedErrorOn400(): void
    {
        $this->http->queueJson(400, ['status' => 'failed', 'message' => 'Invalid checksum']);

        $this->expectException(InvalidRequestException::class);
        $this->api->postJson(BaseUrls::PANEL, '/x', ['a' => '1']);
    }

    public function testThrowsOnInvalidJsonInSuccessResponse(): void
    {
        $this->http->queueText(200, '<html>oops</html>');

        $this->expectException(ApiServerException::class);
        $this->api->postJson(BaseUrls::PANEL, '/x', ['a' => '1']);
    }

    public function testGetTextReturnsRawBody(): void
    {
        $this->http->queueText(200, "-----BEGIN PUBLIC KEY-----\nabc\n-----END PUBLIC KEY-----");

        $pem = $this->api->getText(BaseUrls::API_PAYMENTS, '/api/v1_0/cards/public-key');

        self::assertStringContainsString('BEGIN PUBLIC KEY', $pem);
        self::assertSame('GET', $this->http->lastRequest()->getMethod());
        self::assertNull($this->http->lastRequest()->getBody());
    }

    public function testSendRawDoesNotThrowOnHttpError(): void
    {
        $this->http->queueJson(400, ['refund' => false]);

        $response = $this->api->sendRaw('POST', BaseUrls::PANEL, '/x', ['a' => '1']);

        self::assertSame(400, $response->getStatus());
    }

    public function testFloatAmountsEncodeShortestFormRegardlessOfIni(): void
    {
        $previous = ini_set('serialize_precision', '17');
        try {
            $this->http->queueJson(200, ['status' => 'success']);

            $this->api->postJson(BaseUrls::API_PAYMENTS, '/x', ['amount' => 59.99]);

            self::assertSame('{"amount":59.99}', $this->http->lastRequest()->getBody());
        } finally {
            if ($previous !== false) {
                ini_set('serialize_precision', $previous);
            }
        }
    }
}

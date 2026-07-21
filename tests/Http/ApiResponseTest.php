<?php

declare(strict_types=1);

namespace DPay\Tests\Http;

use DPay\Http\ApiResponse;
use PHPUnit\Framework\TestCase;

final class ApiResponseTest extends TestCase
{
    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $response = new ApiResponse(429, ['Retry-After' => '30', 'X-RateLimit-Limit' => '120'], '{}');
        self::assertSame('30', $response->getHeader('retry-after'));
        self::assertSame('120', $response->getHeader('X-RATELIMIT-LIMIT'));
        self::assertNull($response->getHeader('missing'));
    }

    public function testDecodeJsonReturnsArray(): void
    {
        $response = new ApiResponse(200, [], '{"status":"success","refund":true}');
        self::assertSame(['status' => 'success', 'refund' => true], $response->decodeJson());
    }

    public function testDecodeJsonReturnsNullOnInvalidBody(): void
    {
        self::assertNull((new ApiResponse(200, [], 'not-json'))->decodeJson());
        self::assertNull((new ApiResponse(200, [], '"scalar"'))->decodeJson());
        self::assertNull((new ApiResponse(200, [], ''))->decodeJson());
    }

    public function testDecodeJsonAcceptsTopLevelList(): void
    {
        $response = new ApiResponse(200, [], '[{"id":"1"}]');
        self::assertSame([['id' => '1']], $response->decodeJson());
    }
}

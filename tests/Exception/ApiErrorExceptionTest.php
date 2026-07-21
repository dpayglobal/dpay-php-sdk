<?php

declare(strict_types=1);

namespace DPay\Tests\Exception;

use DPay\Exception\ApiErrorException;
use DPay\Exception\ExceptionInterface;
use DPay\Exception\InvalidRequestException;
use DPay\Exception\RateLimitException;
use DPay\Exception\TransportException;
use PHPUnit\Framework\TestCase;

final class ApiErrorExceptionTest extends TestCase
{
    public function testCarriesHttpContext(): void
    {
        $exception = new ApiErrorException(
            'Invalid checksum',
            400,
            'err01',
            ['value' => ['The value field is required.']],
            '{"status":"failed"}'
        );
        self::assertSame('Invalid checksum', $exception->getMessage());
        self::assertSame(400, $exception->getHttpStatus());
        self::assertSame('err01', $exception->getErrorCode());
        self::assertSame(['value' => ['The value field is required.']], $exception->getFieldErrors());
        self::assertSame('{"status":"failed"}', $exception->getRawBody());
    }

    public function testDefaults(): void
    {
        $exception = new ApiErrorException('Boom', 500);
        self::assertNull($exception->getErrorCode());
        self::assertSame([], $exception->getFieldErrors());
        self::assertSame('', $exception->getRawBody());
    }

    public function testHierarchy(): void
    {
        self::assertInstanceOf(ApiErrorException::class, new InvalidRequestException('x', 422));
        self::assertInstanceOf(ExceptionInterface::class, new TransportException('x'));
        self::assertInstanceOf(ApiErrorException::class, new RateLimitException('x', 429));
    }

    public function testRateLimitExtras(): void
    {
        $exception = new RateLimitException('Too many requests', 429, 30, 120, 0, '{}');
        self::assertSame(30, $exception->getRetryAfter());
        self::assertSame(120, $exception->getLimit());
        self::assertSame(0, $exception->getRemaining());
        self::assertSame(429, $exception->getHttpStatus());
    }
}

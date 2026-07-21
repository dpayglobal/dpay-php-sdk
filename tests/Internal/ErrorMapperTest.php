<?php

declare(strict_types=1);

namespace DPay\Tests\Internal;

use DPay\Exception\ApiServerException;
use DPay\Exception\AuthenticationException;
use DPay\Exception\InvalidRequestException;
use DPay\Exception\NotFoundException;
use DPay\Exception\PermissionException;
use DPay\Exception\RateLimitException;
use DPay\Http\ApiResponse;
use DPay\Internal\ErrorMapper;
use PHPUnit\Framework\TestCase;

final class ErrorMapperTest extends TestCase
{
    private ErrorMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ErrorMapper();
    }

    public function testMaps400WithStringFieldErrors(): void
    {
        $body = '{"status":"failed","message":"Invalid checksum","errors":{"value":"The value field is required."}}';
        $exception = $this->mapper->map(new ApiResponse(400, [], $body));
        self::assertInstanceOf(InvalidRequestException::class, $exception);
        self::assertSame('Invalid checksum', $exception->getMessage());
        self::assertSame(['value' => ['The value field is required.']], $exception->getFieldErrors());
        self::assertSame($body, $exception->getRawBody());
    }

    public function testMaps422WithArrayFieldErrors(): void
    {
        $body = '{"message":"Validation failed","errors":{"email":["Invalid email","Too long"]}}';
        $exception = $this->mapper->map(new ApiResponse(422, [], $body));
        self::assertInstanceOf(InvalidRequestException::class, $exception);
        self::assertSame(['email' => ['Invalid email', 'Too long']], $exception->getFieldErrors());
    }

    public function testMaps401(): void
    {
        $exception = $this->mapper->map(new ApiResponse(401, [], '{"message":"Unauthorized request"}'));
        self::assertInstanceOf(AuthenticationException::class, $exception);
        self::assertSame('Unauthorized request', $exception->getMessage());
    }

    public function testMaps403WithErrorCode(): void
    {
        $body = '{"error":true,"errorcode":"err01","message":"Access denied","status":false}';
        $exception = $this->mapper->map(new ApiResponse(403, [], $body));
        self::assertInstanceOf(PermissionException::class, $exception);
        self::assertSame('err01', $exception->getErrorCode());
    }

    public function testMaps404(): void
    {
        self::assertInstanceOf(
            NotFoundException::class,
            $this->mapper->map(new ApiResponse(404, [], '{"message":"Not found"}'))
        );
    }

    public function testMaps429WithHeaders(): void
    {
        $response = new ApiResponse(
            429,
            ['Retry-After' => '30', 'X-RateLimit-Limit' => '120', 'X-RateLimit-Remaining' => '0'],
            '{"message":"Too Many Attempts."}'
        );
        $exception = $this->mapper->map($response);
        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertSame(30, $exception->getRetryAfter());
        self::assertSame(120, $exception->getLimit());
        self::assertSame(0, $exception->getRemaining());
    }

    public function testMaps500(): void
    {
        $exception = $this->mapper->map(new ApiResponse(500, [], 'Internal Server Error'));
        self::assertInstanceOf(ApiServerException::class, $exception);
        self::assertSame(500, $exception->getHttpStatus());
    }

    public function testFallsBackToMsgKey(): void
    {
        $exception = $this->mapper->map(new ApiResponse(400, [], '{"error":true,"msg":"Bad request"}'));
        self::assertSame('Bad request', $exception->getMessage());
    }

    public function testNormalizeFieldErrorsDropsNonScalarAndNullMessages(): void
    {
        $body = '{"message":"Validation failed","errors":{"a":["ok",["nested"]],"b":null,"c":5}}';
        $exception = $this->mapper->map(new ApiResponse(400, [], $body));
        self::assertInstanceOf(InvalidRequestException::class, $exception);
        self::assertSame(['a' => ['ok']], $exception->getFieldErrors());
    }
}

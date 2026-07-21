<?php

declare(strict_types=1);

namespace DPay\Tests\Refund;

use DPay\Config;
use DPay\Exception\AuthenticationException;
use DPay\Internal\ApiRequestor;
use DPay\Money;
use DPay\Refund\RefundService;
use DPay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

final class RefundServiceTest extends TestCase
{
    private MockHttpClient $http;

    private RefundService $service;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $config = Config::fromArray(['service' => 'MyShop', 'secret_hash' => 'secret123']);
        $this->service = new RefundService(new ApiRequestor($config, $this->http));
    }

    public function testFullRefund(): void
    {
        $this->http->queueJson(200, ['status' => 'success', 'refund' => true, 'message' => 'MSG-1']);

        $refund = $this->service->create('30D9493D-1D73-3FBD-A5D4-633723CC7A68');

        self::assertTrue($refund->isAccepted());
        self::assertSame('MSG-1', $refund->getMessage());
        $body = $this->http->lastRequestBody();
        self::assertSame(['service', 'transaction_id', 'checksum'], array_keys($body));
        self::assertSame(
            '3b345f55900ae6a43220dd14a3827ed9a26a02a3eb46b856d302e2baa44b48a4',
            $body['checksum']
        );
    }

    public function testPartialRefundWithReasonKeepsChecksumOrder(): void
    {
        $this->http->queueJson(200, ['status' => 'success', 'refund' => true, 'message' => 'MSG-2']);

        $this->service->create('30D9493D-1D73-3FBD-A5D4-633723CC7A68', Money::pln(1500), 'reklamacja');

        $body = $this->http->lastRequestBody();
        self::assertSame(['service', 'transaction_id', 'value', 'reason', 'checksum'], array_keys($body));
        self::assertSame('15.00', $body['value']);
        self::assertSame(
            '5c00684addf39fda89d3d6cff88abf26bb8173119056fd3012050422943c798a',
            $body['checksum']
        );
    }

    public function testAvailabilityPositive(): void
    {
        $this->http->queueJson(200, ['status' => 'success', 'refund' => true, 'message' => 'Refund available']);

        $availability = $this->service->checkAvailability('TX-1');

        self::assertTrue($availability->isAvailable());
    }

    public function testAvailabilityNegativeOn400IsNotAnException(): void
    {
        $this->http->queueJson(400, [
            'status' => 'error',
            'refund' => false,
            'message' => 'Refund amount exceeds available refund amount',
        ]);

        $availability = $this->service->checkAvailability('TX-1', Money::pln(999999));

        self::assertFalse($availability->isAvailable());
        self::assertSame('Refund amount exceeds available refund amount', $availability->getMessage());
    }

    public function testAvailabilityBadChecksumThrows(): void
    {
        $this->http->queueJson(401, ['status' => 'error', 'refund' => false, 'message' => 'Unauthorized request']);

        $this->expectException(AuthenticationException::class);
        $this->service->checkAvailability('TX-1');
    }

    public function testAvailabilityUnpaidTransactionOn402IsNotAnException(): void
    {
        $this->http->queueJson(402, [
            'status' => 'error',
            'refund' => false,
            'message' => 'Transakcja nie została opłacona',
        ]);

        $availability = $this->service->checkAvailability('TX-1');

        self::assertFalse($availability->isAvailable());
        self::assertSame('Transakcja nie została opłacona', $availability->getMessage());
        self::assertSame(402, $availability->getHttpStatus());
    }

    public function testAvailabilityAlreadyRefundedOn410IsNotAnException(): void
    {
        $this->http->queueJson(410, [
            'status' => 'error',
            'refund' => false,
            'message' => 'Transakcja została zwrócona wcześniej',
        ]);

        $availability = $this->service->checkAvailability('TX-1');

        self::assertFalse($availability->isAvailable());
        self::assertSame(410, $availability->getHttpStatus());
    }

    public function testAvailabilityNonRefundableChannelOnBusiness401IsNotAnException(): void
    {
        $this->http->queueJson(401, [
            'status' => 'error',
            'refund' => false,
            'message' => 'Transakcji dokonanych kanałami paysafecard nie można zwrócić',
        ]);

        $availability = $this->service->checkAvailability('TX-1');

        self::assertFalse($availability->isAvailable());
        self::assertSame(401, $availability->getHttpStatus());
    }
}

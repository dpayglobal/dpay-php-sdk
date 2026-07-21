<?php

declare(strict_types=1);

namespace DPay\Tests\Payout;

use DPay\Config;
use DPay\Internal\ApiRequestor;
use DPay\Payout\PayoutService;
use DPay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

final class PayoutServiceTest extends TestCase
{
    private MockHttpClient $http;

    private PayoutService $service;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $config = Config::fromArray(['service' => 'MyShop', 'secret_hash' => 'secret123']);
        $this->service = new PayoutService(new ApiRequestor($config, $this->http));
    }

    public function testDetails(): void
    {
        $this->http->queueJson(200, [
            'id' => 12345,
            'state' => 1,
            'net' => 1.23,
            'fee' => 0.10,
            'gross' => 1.33,
            'creation_date' => '2026-07-01 10:00:00',
            'direct_settlement' => 1,
            'declined' => 0,
            'receiver' => [
                'nrb' => 'PL61109010140000071219812874',
                'title' => 'FV 2026/06/001',
                'amount' => 1.23,
                'service' => 'MyShop',
                'receiverName' => 'Firma sp. z o.o.',
                'receiverAddress' => 'ul. Testowa 1',
            ],
        ]);

        $details = $this->service->details(12345);

        self::assertSame(12345, $details->getId());
        self::assertTrue($details->isProcessed());
        self::assertFalse($details->isDeclined());
        self::assertSame(123, $details->getNet()->getMinor());
        self::assertSame(10, $details->getFee()->getMinor());
        self::assertSame(133, $details->getGross()->getMinor());
        self::assertTrue($details->isDirectSettlement());
        $receiver = $details->getReceiver();
        self::assertNotNull($receiver);
        self::assertSame('PL61109010140000071219812874', $receiver->getNrb());

        $body = $this->http->lastRequestBody();
        self::assertSame(['service', 'withdraw_id', 'checksum'], array_keys($body));
        self::assertSame(
            '9c7f5305d2de640ed3d1915f6ca33d6040b1583fab56676d0fcdcf65765b223d',
            $body['checksum']
        );
    }

    public function testDetailsWithTimestampKeepsOrder(): void
    {
        $this->http->queueJson(200, ['id' => 12345, 'state' => 0]);

        $this->service->details(12345, 1753100000);

        $body = $this->http->lastRequestBody();
        self::assertSame(['service', 'timestamp', 'withdraw_id', 'checksum'], array_keys($body));
    }

    public function testDeclinedPayout(): void
    {
        $this->http->queueJson(200, [
            'id' => 12345,
            'state' => -1,
            'declined' => 1,
            'decline_reason' => 'Bad IBAN',
            'decline_status' => 'REJECTED',
        ]);

        $details = $this->service->details(12345);

        self::assertTrue($details->isFailed());
        self::assertTrue($details->isDeclined());
        self::assertSame('Bad IBAN', $details->getDeclineReason());
    }

    public function testMalformedReceiverAmountIsNullWithoutThrowing(): void
    {
        $this->http->queueJson(200, [
            'id' => 12345,
            'state' => 1,
            'receiver' => [
                'nrb' => 'PL61109010140000071219812874',
                'amount' => 'N/A',
            ],
        ]);

        $details = $this->service->details(12345);

        $receiver = $details->getReceiver();
        self::assertNotNull($receiver);
        self::assertNull($receiver->getAmount());
    }
}

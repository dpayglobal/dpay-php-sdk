<?php

declare(strict_types=1);

namespace DPay\Tests\Recurring;

use DPay\Config;
use DPay\Exception\InvalidRequestException;
use DPay\Internal\ApiRequestor;
use DPay\Recurring\RecurringService;
use DPay\Recurring\RecurringStatus;
use DPay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

final class RecurringServiceTest extends TestCase
{
    private const TRANSACTION_ID = 'A75AEBB4-4B89-4834-AD43-EF442C133769';

    private MockHttpClient $http;

    private RecurringService $service;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $config = Config::fromArray(['service' => 'sdk-test-service', 'secret_hash' => 'sdk-test-hash-0001']);
        $this->service = new RecurringService(new ApiRequestor($config, $this->http));
    }

    public function testStatusReturnsTheAliasAndTerms(): void
    {
        $this->http->queueJson(200, ['status' => 'success', 'data' => [
            'alias' => 'SUB-0001', 'method' => 'blik', 'status' => 'ACTIVE', 'expiration_date' => '2027-09-30',
            'registration' => ['transaction_id' => self::TRANSACTION_ID, 'label' => 'Abonament', 'model' => 'A',
                'frequency' => '1M', 'limit_amt' => 5999, 'tot_limit_amt' => 71988, 'is_limit_amt_fixed' => true,
                'init_date' => '2026-11-01', 'terms_url' => 'https://shop.example/terms', 'terms_version' => '2026-09',
                'registered_at' => '2026-09-26T12:00:00+02:00'],
        ]]);

        $status = $this->service->status('SUB-0001');

        self::assertSame('https://api-payments.dpay.pl/api/v1_0/payments/recurring/status', $this->http->lastRequest()->getUrl());
        self::assertSame([
            'service' => 'sdk-test-service',
            'alias' => 'SUB-0001',
            'checksum' => '01e38925de1ceefbfbaffdf84a917f1c95123403a0e51a7d74cb81f227c3e3ef',
        ], $this->http->lastRequestBody());
        self::assertTrue($status->isActive());
        self::assertSame('blik', $status->getMethod());
        self::assertSame('2027-09-30', $status->getExpirationDate());
        $registration = $status->getRegistration();
        self::assertNotNull($registration);
        self::assertSame(self::TRANSACTION_ID, $registration->getTransactionId());
        self::assertSame(5999, $registration->getLimitAmt());
        self::assertTrue($registration->isLimitAmtFixed());
        self::assertSame('https://shop.example/terms', $registration->getTermsUrl());
    }

    public function testCancelSignsTheOperation(): void
    {
        $this->http->queueJson(200, ['status' => 'success', 'data' => ['alias' => 'SUB-0001', 'status' => 'UNREGISTERED']]);

        $status = $this->service->cancel('SUB-0001', 'Rezygnacja');

        self::assertSame(RecurringStatus::UNREGISTERED, $status);
        self::assertSame('https://api-payments.dpay.pl/api/v1_0/payments/recurring/cancel', $this->http->lastRequest()->getUrl());
        self::assertSame([
            'service' => 'sdk-test-service',
            'alias' => 'SUB-0001',
            'reason' => 'Rezygnacja',
            // sha256(service|hash|alias|cancel) - suma statusu nie anuluje
            'checksum' => '848c236b3060a94c2ed14c150de154ee7aa2d64ec3682771259c30321cadf82b',
        ], $this->http->lastRequestBody());
    }

    public function testRetryReturnsThePendingRetry(): void
    {
        $this->http->queueJson(200, ['status' => 'success', 'data' => ['transactionId' => self::TRANSACTION_ID, 'retry' => ['status' => 'pending', 'count' => 1]]]);

        $result = $this->service->retry(self::TRANSACTION_ID);

        self::assertSame([
            'service' => 'sdk-test-service',
            'transaction_id' => self::TRANSACTION_ID,
            'checksum' => '00bceec5fca2ee4c3a1737156df376441d225790eb61ca47fcbe9cba7243a77d',
        ], $this->http->lastRequestBody());
        self::assertTrue($result->isPending());
        self::assertSame(1, $result->getCount());
        self::assertSame(self::TRANSACTION_ID, $result->getTransactionId());
    }

    public function testRetryDeclinedAtOnceIsAResultNotAnError(): void
    {
        $this->http->queueJson(200, ['status' => 'success', 'data' => ['transactionId' => self::TRANSACTION_ID,
            'retry' => ['status' => 'failed', 'count' => 2, 'error' => 'INSUFFICIENT_FUNDS', 'error_description' => 'IssId: 1']]]);

        $result = $this->service->retry(self::TRANSACTION_ID);

        self::assertTrue($result->isFailed());
        self::assertSame('INSUFFICIENT_FUNDS', $result->getErrorCode());
        self::assertSame('IssId: 1', $result->getErrorDescription());
    }

    public function testRetryNotAllowedCarriesTheReason(): void
    {
        $this->http->queueJson(400, ['status' => 'failed', 'message' => 'Recurring charge cannot be retried (DECLINE_NOT_RETRYABLE).',
            'errors' => ['retry' => 'DECLINE_NOT_RETRYABLE', 'decline_reason' => 'SEC_DECLINED']]);

        try {
            $this->service->retry(self::TRANSACTION_ID);
            self::fail('Expected InvalidRequestException');
        } catch (InvalidRequestException $exception) {
            self::assertSame(['DECLINE_NOT_RETRYABLE'], $exception->getFieldErrors()['retry']);
            self::assertSame(400, $exception->getHttpStatus());
        }
    }
}

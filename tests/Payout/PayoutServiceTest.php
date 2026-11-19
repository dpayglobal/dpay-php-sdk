<?php

declare(strict_types=1);

namespace DPay\Tests\Payout;

use DPay\Config;
use DPay\Exception\NotFoundException;
use DPay\Internal\ApiRequestor;
use DPay\Money;
use DPay\Payout\PayoutService;
use DPay\Payout\PayoutState;
use DPay\Payout\PayoutType;
use DPay\Tests\Support\MockHttpClient;
use InvalidArgumentException;
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

    public function testDetailsOfARegularPayoutIsPaysafecardAware(): void
    {
        $this->http->queueJson(200, [
            'id' => 12346,
            'state' => 0,
            'net' => 5.0,
            'fee' => 0.5,
            'gross' => 5.5,
            'creation_date' => '2026-07-01 10:00:00',
            'direct_settlement' => 0,
            'paysafecard' => 1,
            'nrb' => 'PL61109010140000071219812874',
            'declined' => 0,
        ]);

        $details = $this->service->details(12346);

        self::assertTrue($details->isPaysafecard());
        self::assertTrue($details->isWaiting());
        self::assertSame('PLN', $details->getCurrency());
        self::assertNull($details->getExportDate());
    }

    public function testListSendsFiltersInChecksumOrder(): void
    {
        $this->http->queueJson(200, [
            'withdraws' => [[
                'id' => 12345,
                'type' => 'standard',
                'state' => 1,
                'net' => '27.15',
                'fee' => '3.06',
                'gross' => '30.21',
                'currency_code' => 'EUR',
                'creation_date' => '2026-08-01 10:00:00',
                'export_date' => '2026-08-01 14:20:00',
                'declined' => 0,
            ]],
            'meta' => ['page' => 2, 'per_page' => 10, 'total' => 11, 'total_pages' => 2],
        ]);

        $page = $this->service->list(
            ['type' => PayoutType::STANDARD, 'state' => PayoutState::PROCESSED, 'date_to' => '2026-08-31 23:59:59', 'date_from' => '2026-08-01'],
            2,
            10
        );

        $body = $this->http->lastRequestBody();
        self::assertSame('https://panel.dpay.pl/api/v1/pbl/withdraws', $this->http->lastRequest()->getUrl());
        self::assertSame(['service', 'date_from', 'date_to', 'state', 'type', 'page', 'per_page', 'checksum'], array_keys($body));
        self::assertSame(1, $body['state']);
        self::assertSame(
            hash('sha256', 'MyShop|2026-08-01|2026-08-31 23:59:59|1|standard|2|10|secret123'),
            $body['checksum']
        );
        self::assertSame('5be864bc1b81a002fe134dbaed5db1d6517db76fdc952842b756da253ceb504b', $body['checksum']);

        $items = $page->getItems();
        self::assertCount(1, $items);
        self::assertSame(12345, $items[0]->getId());
        self::assertSame(PayoutType::STANDARD, $items[0]->getType());
        self::assertTrue($items[0]->isProcessed());
        self::assertFalse($items[0]->isDeclined());
        self::assertTrue($items[0]->getNet()->equals(Money::of(2715, 'EUR')));
        self::assertTrue($items[0]->getFee()->equals(Money::of(306, 'EUR')));
        self::assertTrue($items[0]->getGross()->equals(Money::of(3021, 'EUR')));
        self::assertSame('2026-08-01 14:20:00', $items[0]->getExportDate());
        self::assertSame(2, $page->getMeta()->getPage());
        self::assertSame(11, $page->getMeta()->getTotal());
        self::assertFalse($page->hasMore());
    }

    public function testMinimalListSignsServiceOnly(): void
    {
        $this->http->queueJson(200, ['withdraws' => [], 'meta' => ['page' => 1, 'per_page' => 50, 'total' => 0, 'total_pages' => 0]]);

        $page = $this->service->list();

        $body = $this->http->lastRequestBody();
        self::assertSame(['service', 'checksum'], array_keys($body));
        self::assertSame(hash('sha256', 'MyShop|secret123'), $body['checksum']);
        self::assertSame([], $page->getItems());
        self::assertFalse($page->hasMore());
    }

    public function testFailedStateFilterSignsMinusOne(): void
    {
        $this->http->queueJson(200, ['withdraws' => [], 'meta' => ['page' => 1, 'per_page' => 50, 'total' => 0, 'total_pages' => 0]]);

        $this->service->list(['state' => PayoutState::FAILED]);

        self::assertSame(hash('sha256', 'MyShop|-1|secret123'), $this->http->lastRequestBody()['checksum']);
    }

    public function testDateFiltersAreSentTrimmedBecauseTheApiTrimsBeforeTheChecksum(): void
    {
        $this->http->queueJson(200, ['withdraws' => [], 'meta' => ['page' => 1, 'per_page' => 50, 'total' => 0, 'total_pages' => 0]]);

        $this->service->list(['date_from' => " 2026-08-01\n", 'date_to' => '2026-08-31 23:59:59 ']);

        $body = $this->http->lastRequestBody();
        self::assertSame('2026-08-01', $body['date_from']);
        self::assertSame('2026-08-31 23:59:59', $body['date_to']);
        self::assertSame(hash('sha256', 'MyShop|2026-08-01|2026-08-31 23:59:59|secret123'), $body['checksum']);
    }

    public function testIterateWalksAllPages(): void
    {
        $row = static fn (int $id): array => ['id' => $id, 'type' => 'direct', 'state' => 0, 'net' => '1.00', 'fee' => '0.00', 'gross' => '1.00', 'currency_code' => 'PLN', 'declined' => 0];
        $this->http->queueJson(200, ['withdraws' => [$row(3), $row(2)], 'meta' => ['page' => 1, 'per_page' => 2, 'total' => 3, 'total_pages' => 2]]);
        $this->http->queueJson(200, ['withdraws' => [$row(1)], 'meta' => ['page' => 2, 'per_page' => 2, 'total' => 3, 'total_pages' => 2]]);

        $ids = [];
        foreach ($this->service->iterate(['type' => PayoutType::DIRECT], 2) as $item) {
            $ids[] = $item->getId();
        }

        self::assertSame([3, 2, 1], $ids);
        self::assertCount(2, $this->http->requests());
        self::assertSame(2, $this->http->lastRequestBody()['page']);
    }

    /**
     * @dataProvider invalidListArguments
     *
     * @param array{date_from?: string, date_to?: string, state?: int, type?: string} $filters
     */
    public function testListValidation(array $filters, ?int $page, ?int $perPage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->list($filters, $page, $perPage);
    }

    /**
     * @return array<string, array{array{date_from?: string, date_to?: string, state?: int, type?: string}, int|null, int|null}>
     */
    public static function invalidListArguments(): array
    {
        return [
            'state' => [['state' => 2], null, null],
            'type' => [['type' => 'instant'], null, null],
            'empty date' => [['date_from' => ' '], null, null],
            'page 0' => [[], 0, null],
            'per_page 201' => [[], null, 201],
            'per_page 0' => [[], null, 0],
        ];
    }

    public function testListRejectsUnknownFilters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown payout filter "status"');
        $this->service->list(['status' => 1]);
    }

    public function testDetailsFull(): void
    {
        $this->http->queueJson(200, [
            'settlement' => [
                'id' => 12345,
                'state' => 1,
                'net' => '27.15',
                'fee' => '3.06',
                'gross' => '30.21',
                'currency_code' => 'PLN',
                'creation_date' => '2026-08-01 10:00:00',
                'export_date' => '2026-08-01 14:20:00',
                'direct_settlement' => 0,
                'declined' => 0,
                'paysafecard' => 0,
                'nrb' => 'PL61109010140000071219812874',
            ],
            'summary' => ['net' => '27.15', 'fee' => '3.06', 'gross' => '30.21', 'count' => 3],
            'transactions' => [[
                'payment_id' => '8F2C1A90-5B7E-4C31-9A02-7D6E4B1F3C88',
                'date' => '2026-07-31 12:04:11',
                'net' => '9.05',
                'fee' => '1.02',
                'gross' => '10.07',
                'service_id' => 42,
                'service_name' => 'Sklep testowy',
                'custom' => 'order-1234',
                'ipksef' => null,
            ]],
            'meta' => ['page' => 1, 'per_page' => 1, 'total' => 3, 'total_pages' => 3],
        ]);

        $settlement = $this->service->detailsFull(12345, 1, 1);

        $body = $this->http->lastRequestBody();
        self::assertSame('https://panel.dpay.pl/api/v1/pbl/withdraws/details/full', $this->http->lastRequest()->getUrl());
        self::assertSame(['service', 'withdraw_id', 'page', 'per_page', 'checksum'], array_keys($body));
        self::assertSame(hash('sha256', 'MyShop|12345|1|1|secret123'), $body['checksum']);

        $header = $settlement->getSettlement();
        self::assertSame(12345, $header->getId());
        self::assertTrue($header->isProcessed());
        self::assertFalse($header->isPaysafecard());
        self::assertSame('2026-08-01 14:20:00', $header->getExportDate());
        self::assertSame('PL61109010140000071219812874', $header->getNrb());
        self::assertSame(2715, $settlement->getSummaryNet()->getMinor());
        self::assertSame(306, $settlement->getSummaryFee()->getMinor());
        self::assertSame(3021, $settlement->getSummaryGross()->getMinor());
        self::assertSame(3, $settlement->getSummaryCount());
        $positions = $settlement->getPositions();
        self::assertCount(1, $positions);
        self::assertSame('8F2C1A90-5B7E-4C31-9A02-7D6E4B1F3C88', $positions[0]->getPaymentId());
        self::assertSame('2026-07-31 12:04:11', $positions[0]->getDate());
        self::assertSame(905, $positions[0]->getNet()->getMinor());
        self::assertSame(102, $positions[0]->getFee()->getMinor());
        self::assertSame(1007, $positions[0]->getGross()->getMinor());
        self::assertSame(42, $positions[0]->getServiceId());
        self::assertSame('Sklep testowy', $positions[0]->getServiceName());
        self::assertSame('order-1234', $positions[0]->getCustom());
        self::assertNull($positions[0]->getIpksef());
        self::assertTrue($settlement->getMeta()->hasMore());
    }

    public function testDetailsFullInForeignCurrencyAndMinimalBody(): void
    {
        $this->http->queueJson(200, [
            'settlement' => [
                'id' => 7, 'state' => 0, 'net' => '10.00', 'fee' => '0.00', 'gross' => '10.00', 'currency_code' => 'EUR',
                'direct_settlement' => 1, 'declined' => 0,
                'receiver' => ['nrb' => 'PL61109010140000071219812874', 'title' => 'FV 1', 'receiverName' => 'Firma', 'receiverAddress' => 'Lisboa'],
            ],
            'summary' => ['net' => '10.00', 'fee' => '0.00', 'gross' => '10.00', 'count' => 1],
            'transactions' => [['payment_id' => 'P-1', 'date' => null, 'net' => '10.00', 'fee' => '0.00', 'gross' => '10.00']],
            'meta' => ['page' => 1, 'per_page' => 100, 'total' => 1, 'total_pages' => 1],
        ]);

        $settlement = $this->service->detailsFull(7);

        self::assertSame(['service', 'withdraw_id', 'checksum'], array_keys($this->http->lastRequestBody()));
        self::assertSame(hash('sha256', 'MyShop|7|secret123'), $this->http->lastRequestBody()['checksum']);
        self::assertSame('EUR', $settlement->getSettlement()->getCurrency());
        self::assertSame('EUR', $settlement->getSummaryNet()->getCurrency());
        self::assertSame('EUR', $settlement->getPositions()[0]->getNet()->getCurrency());
        self::assertNull($settlement->getPositions()[0]->getServiceId());
        $receiver = $settlement->getSettlement()->getReceiver();
        self::assertNotNull($receiver);
        self::assertSame('Firma', $receiver->getReceiverName());
        self::assertFalse($settlement->getMeta()->hasMore());
    }

    public function testDetailsFullValidatesPerPageAndMapsNotFound(): void
    {
        try {
            $this->service->detailsFull(1, null, 501);
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('per_page must be between 1 and 500', $exception->getMessage());
        }

        $this->http->queueJson(404, ['status' => 'error', 'message' => 'Withdraw does not exist!']);
        $this->expectException(NotFoundException::class);
        $this->service->detailsFull(999);
    }

    public function testBodiesMatchTheSharedVectors(): void
    {
        $http = new MockHttpClient();
        $service = new PayoutService(new ApiRequestor(
            Config::fromArray(['service' => \DPay\Tests\Support\ApiVectors::service(), 'secret_hash' => \DPay\Tests\Support\ApiVectors::secretHash()]),
            $http
        ));
        $vectors = [];
        foreach (\DPay\Tests\Support\ApiVectors::orderedBody() as $vector) {
            $vectors[$vector['name']] = $vector;
        }
        $empty = ['withdraws' => [], 'meta' => ['page' => 1, 'per_page' => 50, 'total' => 0, 'total_pages' => 0]];
        $full = ['settlement' => [], 'summary' => [], 'transactions' => [], 'meta' => []];

        $calls = [
            'payouts_list_minimal' => static function () use ($service): void {
                $service->list();
            },
            'payouts_list_all_filters' => static function () use ($service): void {
                $service->list(['date_from' => '2026-08-01', 'date_to' => '2026-08-31 23:59:59', 'state' => 1, 'type' => 'standard'], 2, 10);
            },
            'payouts_list_state_failed' => static function () use ($service): void {
                $service->list(['state' => PayoutState::FAILED]);
            },
            'payouts_list_state_waiting_page_1' => static function () use ($service): void {
                $service->list(['state' => PayoutState::WAITING], 1);
            },
            'payouts_details_full_minimal' => static function () use ($service): void {
                $service->detailsFull(12345);
            },
            'payouts_details_full_page' => static function () use ($service): void {
                $service->detailsFull(12345, 2, 500);
            },
        ];
        foreach ($calls as $name => $call) {
            $http->queueJson(200, strpos($name, 'payouts_list') === 0 ? $empty : $full);
            $call();
            $body = $http->lastRequestBody();
            $checksum = $body['checksum'];
            unset($body['checksum']);
            self::assertSame($vectors[$name]['body'], $body, $name);
            self::assertSame($vectors[$name]['checksum'], $checksum, $name);
        }
    }
}

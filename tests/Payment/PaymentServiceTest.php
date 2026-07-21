<?php

declare(strict_types=1);

namespace DPay\Tests\Payment;

use DPay\Config;
use DPay\Exception\PaymentRejectedException;
use DPay\Internal\ApiRequestor;
use DPay\Money;
use DPay\Payment\PaymentService;
use DPay\Payment\RegisterPaymentRequest;
use DPay\Payment\ReturnUrls;
use DPay\Payment\TransactionType;
use DPay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

final class PaymentServiceTest extends TestCase
{
    private MockHttpClient $http;

    private PaymentService $service;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $config = Config::fromArray(['service' => 'MyShop', 'secret_hash' => 'secret123']);
        $this->service = new PaymentService(new ApiRequestor($config, $this->http));
    }

    private function request(): RegisterPaymentRequest
    {
        return RegisterPaymentRequest::create(
            Money::pln(1000),
            TransactionType::TRANSFERS,
            new ReturnUrls('https://shop.example/ok', 'https://shop.example/fail', 'https://shop.example/ipn')
        );
    }

    public function testRegisterSendsChecksumAndParsesRedirect(): void
    {
        $this->http->queueJson(200, [
            'error' => false,
            'msg' => 'https://secure.dpay.pl/transfer@pay@30D9493D-1D73-3FBD-A5D4-633723CC7A68',
            'status' => true,
            'transactionId' => '30D9493D-1D73-3FBD-A5D4-633723CC7A68',
        ]);

        $payment = $this->service->register($this->request());

        self::assertSame('30D9493D-1D73-3FBD-A5D4-633723CC7A68', $payment->getTransactionId());
        self::assertSame(
            'https://secure.dpay.pl/transfer@pay@30D9493D-1D73-3FBD-A5D4-633723CC7A68',
            $payment->getRedirectUrl()
        );
        self::assertFalse($payment->isPaid());

        $request = $this->http->lastRequest();
        self::assertSame('https://api-payments.dpay.pl/api/v1_0/payments/register', $request->getUrl());
        $body = $this->http->lastRequestBody();
        self::assertSame(
            'ae7fd11b457d6fc41edd193dfb0dd42b471bd6d312c1ae12dffeb931371c4cd2',
            $body['checksum']
        );
        self::assertSame('checksum', array_key_last($body));
    }

    public function testRegisterPaidInline(): void
    {
        $this->http->queueJson(200, [
            'error' => false,
            'msg' => 'Transaction paid',
            'status' => true,
            'transactionId' => 'ABC-123',
        ]);

        $payment = $this->service->register($this->request());

        self::assertTrue($payment->isPaid());
        self::assertNull($payment->getRedirectUrl());
    }

    public function testRegisterRejectionThrows(): void
    {
        $this->http->queueJson(200, [
            'error' => true,
            'msg' => 'Transaction canceled',
            'status' => false,
            'transactionId' => '42191111-A7AE-392E-8C09-7965C1DC6B0B',
            'additionalInfo' => ['error' => 'USER_DECLINED', 'error_description' => null],
        ]);

        try {
            $this->service->register($this->request());
            self::fail('Expected PaymentRejectedException');
        } catch (PaymentRejectedException $exception) {
            self::assertSame('Transaction canceled', $exception->getMessage());
            self::assertSame('USER_DECLINED', $exception->getErrorCode());
            self::assertSame('42191111-A7AE-392E-8C09-7965C1DC6B0B', $exception->getTransactionId());
            self::assertSame(200, $exception->getHttpStatus());
        }
    }

    public function testRegisterExposesRaw(): void
    {
        $this->http->queueJson(200, [
            'error' => false,
            'msg' => 'https://secure.dpay.pl/x',
            'status' => true,
            'transactionId' => 'ABC-123',
            'brand_new_field' => 'value',
        ]);

        $payment = $this->service->register($this->request());

        self::assertSame('value', $payment->getRaw()['brand_new_field']);
    }

    public function testDetailsSendsOrderedChecksum(): void
    {
        $this->http->queueJson(200, [
            'status' => 'success',
            'transaction' => ['id' => '30D9493D-1D73-3FBD-A5D4-633723CC7A68', 'value' => '10.00', 'status' => 'paid'],
            'payer' => [],
            'refunds' => [],
        ]);

        $transaction = $this->service->details('30D9493D-1D73-3FBD-A5D4-633723CC7A68');

        self::assertSame('30D9493D-1D73-3FBD-A5D4-633723CC7A68', $transaction->getId());
        $request = $this->http->lastRequest();
        self::assertSame('https://panel.dpay.pl/api/v1/pbl/details', $request->getUrl());
        $body = $this->http->lastRequestBody();
        self::assertSame(['service', 'transaction_id', 'checksum'], array_keys($body));
        self::assertSame(
            '3b345f55900ae6a43220dd14a3827ed9a26a02a3eb46b856d302e2baa44b48a4',
            $body['checksum']
        );
    }
}

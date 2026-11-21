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
        self::assertNull($payment->getPaidTransaction());
        self::assertNull($payment->getMultibanco());
    }

    public function testRegisterPaidReturnsThePaidTransaction(): void
    {
        $this->http->queueJson(200, [
            'error' => false,
            'msg' => 'Transaction paid',
            'status' => true,
            'transactionId' => '30D9493D-1D73-3FBD-A5D4-633723CC7A68',
            'additionalInfo' => ['transaction' => [
                'payment_id' => '30D9493D-1D73-3FBD-A5D4-633723CC7A68',
                'pid' => null,
                'mtid' => null,
                'email' => 'client@example.com',
                'description' => 'Zamowienie #1234',
                'name' => 'Jan',
                'surname' => 'Kowalski',
                'value' => '10.00',
                'status' => 'paid',
                'creation_date' => '2026-11-18 10:00:00',
                'payment_date' => '2026-11-18 10:01:00',
            ]],
        ]);

        $transaction = $this->service->register($this->request())->getPaidTransaction();

        self::assertNotNull($transaction);
        self::assertSame('30D9493D-1D73-3FBD-A5D4-633723CC7A68', $transaction->getPaymentId());
        self::assertSame(\DPay\Payment\RegisteredTransaction::STATUS_PAID, $transaction->getStatus());
        self::assertTrue($transaction->isPaid());
        $value = $transaction->getValue();
        self::assertNotNull($value);
        self::assertTrue($value->equals(Money::pln(1000)));
        self::assertSame('client@example.com', $transaction->getEmail());
        self::assertSame('Jan', $transaction->getName());
        self::assertSame('Kowalski', $transaction->getSurname());
        self::assertNull($transaction->getPid());
        self::assertSame('2026-11-18 10:01:00', $transaction->getPaymentDate());
    }

    public function testRegisterMultibancoReturnsTheReference(): void
    {
        $this->http->queueJson(200, [
            'error' => false,
            'msg' => 'https://secure.dpay.pl/transfer@pay@ABC',
            'status' => true,
            'transactionId' => 'ABC',
            'multibanco' => [
                'entity' => '12345',
                'reference' => '123456789',
                'amount' => '12.50',
                'currency' => 'EUR',
                'expires_at' => '2026-11-21T10:00:00+00:00',
            ],
        ]);

        $request = RegisterPaymentRequest::create(
            Money::of(1250, \DPay\Currency::EUR),
            TransactionType::TRANSFERS,
            new ReturnUrls('https://shop.example/ok', 'https://shop.example/fail')
        )->withChannel('390')
            ->withPayer(\DPay\Payment\Payer::create()->withEmail('client@example.com')->withName('Joao', 'Silva'))
            ->withAcceptTos(true);
        $multibanco = $this->service->register($request)->getMultibanco();

        self::assertSame('EUR', $this->http->lastRequestBody()['currency_code']);
        self::assertNotNull($multibanco);
        self::assertSame('12345', $multibanco->getEntity());
        self::assertSame('123456789', $multibanco->getReference());
        $amount = $multibanco->getAmount();
        self::assertNotNull($amount);
        self::assertTrue($amount->equals(Money::of(1250, 'EUR')));
        self::assertSame('EUR', $multibanco->getCurrency());
        self::assertSame('2026-11-21T10:00:00+00:00', $multibanco->getExpiresAt());
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

    public function testDetailsSendsTheTransactionIdTrimmedLikeTheApiTrimsIt(): void
    {
        $this->http->queueJson(200, ['status' => 'success', 'transaction' => ['id' => 'X'], 'payer' => [], 'refunds' => []]);

        $this->service->details("\t30D9493D-1D73-3FBD-A5D4-633723CC7A68\u{2028}");

        $body = $this->http->lastRequestBody();
        self::assertSame('30D9493D-1D73-3FBD-A5D4-633723CC7A68', $body['transaction_id']);
        self::assertSame('3b345f55900ae6a43220dd14a3827ed9a26a02a3eb46b856d302e2baa44b48a4', $body['checksum']);
    }

    public function testDetailsRejectsABlankTransactionId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('transaction_id must not be empty');
        $this->service->details("\u{200B}");
    }
}

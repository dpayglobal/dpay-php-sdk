<?php

declare(strict_types=1);

namespace DPay\Tests;

use DPay\Bank\BankService;
use DPay\Blik\BlikService;
use DPay\Card\CardService;
use DPay\DPayClient;
use DPay\Money;
use DPay\Payment\PaymentService;
use DPay\Payment\RegisterPaymentRequest;
use DPay\Payment\ReturnUrls;
use DPay\Payment\TransactionType;
use DPay\Payout\PayoutService;
use DPay\Refund\RefundService;
use DPay\Tests\Support\MockHttpClient;
use DPay\Version;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DPayClientTest extends TestCase
{
    public function testExposesAllServices(): void
    {
        $client = new DPayClient([
            'service' => 'MyShop',
            'secret_hash' => 'secret123',
            'http_client' => new MockHttpClient(),
        ]);

        self::assertInstanceOf(PaymentService::class, $client->payments);
        self::assertInstanceOf(RefundService::class, $client->refunds);
        self::assertInstanceOf(BankService::class, $client->banks);
        self::assertInstanceOf(BlikService::class, $client->blik);
        self::assertInstanceOf(CardService::class, $client->cards);
        self::assertInstanceOf(PayoutService::class, $client->payouts);
        self::assertSame('MyShop', $client->getConfig()->service());
    }

    public function testVersionConstant(): void
    {
        self::assertSame(Version::SDK, DPayClient::VERSION);
    }

    public function testEndToEndRegisterThroughClient(): void
    {
        $http = new MockHttpClient();
        $http->queueJson(200, [
            'error' => false,
            'msg' => 'https://secure.dpay.pl/transfer@pay@ABC-123',
            'status' => true,
            'transactionId' => 'ABC-123',
        ]);

        $client = new DPayClient([
            'service' => 'MyShop',
            'secret_hash' => 'secret123',
            'http_client' => $http,
        ]);

        $payment = $client->payments->register(RegisterPaymentRequest::create(
            Money::pln(1000),
            TransactionType::TRANSFERS,
            new ReturnUrls('https://shop.example/ok', 'https://shop.example/fail', 'https://shop.example/ipn')
        ));

        self::assertSame('ABC-123', $payment->getTransactionId());
        self::assertSame(
            'https://api-payments.dpay.pl/api/v1_0/payments/register',
            $http->lastRequest()->getUrl()
        );
    }

    public function testInvalidConfigThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DPayClient(['service' => 'MyShop']);
    }
}

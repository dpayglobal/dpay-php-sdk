<?php

declare(strict_types=1);

namespace DPay\Tests\Card;

use DPay\Card\CardPaymentRequest;
use DPay\Card\CardService;
use DPay\Card\DccDecision;
use DPay\Card\RedirectType;
use DPay\Config;
use DPay\Exception\CardPaymentException;
use DPay\Internal\ApiRequestor;
use DPay\Payment\DeviceInfo;
use DPay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

final class CardServiceTest extends TestCase
{
    private MockHttpClient $http;

    private CardService $service;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $config = Config::fromArray(['service' => 'MyShop', 'secret_hash' => 'secret123']);
        $this->service = new CardService(new ApiRequestor($config, $this->http));
    }

    private function request(): CardPaymentRequest
    {
        $deviceInfo = DeviceInfo::create(
            'text/html',
            'pl-PL',
            24,
            1200,
            1920,
            -60,
            'Mozilla/5.0',
            'Win32',
            '10.10,10.10',
            'device-1',
            'Netscape'
        );

        return CardPaymentRequest::create($deviceInfo)
            ->withEncryptedCardData('BASE64DATA')
            ->withCardHolder('Jan', 'Kowalski')
            ->withChannelId(31);
    }

    public function testPublicKey(): void
    {
        $this->http->queueText(200, "-----BEGIN PUBLIC KEY-----\nabc\n-----END PUBLIC KEY-----\n");

        $pem = $this->service->publicKey();

        self::assertSame("-----BEGIN PUBLIC KEY-----\nabc\n-----END PUBLIC KEY-----", $pem);
        self::assertSame(
            'https://api-payments.dpay.pl/api/v1_0/cards/public-key',
            $this->http->lastRequest()->getUrl()
        );
    }

    public function testPayOtpSuccess(): void
    {
        $this->http->queueJson(200, [
            'success' => true,
            'status' => 'success',
            'message' => ['redirectText' => '', 'redirectType' => 'SUCCESS'],
        ]);

        $result = $this->service->payOtp('TX-1', $this->request());

        self::assertTrue($result->isSuccess());
        self::assertSame(RedirectType::SUCCESS, $result->getRedirectType());
        $request = $this->http->lastRequest();
        self::assertSame('https://api-payments.dpay.pl/api/v1_0/cards/payment/TX-1/pay/card-otp', $request->getUrl());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame(31, $body['channelId']);
        self::assertSame('BASE64DATA', $body['encryptedCardData']);
        self::assertSame('device-1', $body['deviceInfo']['deviceID']);
    }

    public function testPayOtpThreeDsForm(): void
    {
        $formHtml = '<form method="POST"></form>';
        $this->http->queueJson(200, [
            'success' => true,
            'status' => 'success',
            'message' => ['redirectText' => base64_encode($formHtml), 'redirectType' => 'FORM'],
        ]);

        $result = $this->service->payOtp('TX-1', $this->request());

        self::assertTrue($result->requiresThreeDsForm());
        self::assertSame($formHtml, $result->getThreeDsFormHtml());
        self::assertNull($result->getRedirectUrl());
    }

    public function testPayOtpDccOffer(): void
    {
        $this->http->queueJson(200, [
            'success' => true,
            'status' => 'success',
            'message' => [
                'redirectText' => null,
                'redirectType' => 'DCC_OFFER',
                'dccOffer' => [
                    'currencyConversionId' => '00509166251006151007',
                    'originalAmount' => 3,
                    'originalCurrency' => 'EUR',
                    'convertedAmount' => 13.52,
                    'convertedCurrency' => 'PLN',
                    'exchangeRate' => 4.507968,
                    'validUntil' => '2026-05-03T17:40:07+00:00',
                    'declarationText' => 'Make sure you understand the costs...',
                    'markup' => [['rate' => 6, 'additionalInfo' => 'Mastercard']],
                    'europeanEconomicArea' => true,
                ],
            ],
        ]);

        $result = $this->service->payOtp('TX-1', $this->request());

        self::assertTrue($result->hasDccOffer());
        $offer = $result->getDccOffer();
        self::assertNotNull($offer);
        self::assertSame(300, $offer->getOriginalAmount()->getMinor());
        self::assertSame('EUR', $offer->getOriginalAmount()->getCurrency());
        self::assertSame(1352, $offer->getConvertedAmount()->getMinor());
        self::assertSame('PLN', $offer->getConvertedAmount()->getCurrency());
        self::assertSame(4.507968, $offer->getExchangeRate());
        self::assertTrue($offer->isEuropeanEconomicArea());
        self::assertCount(1, $offer->getMarkup());
        self::assertSame(6.0, $offer->getMarkup()[0]->getRate());
    }

    public function testDccDecisionGoesToBody(): void
    {
        $this->http->queueJson(200, [
            'success' => true,
            'status' => 'success',
            'message' => ['redirectText' => '', 'redirectType' => 'SUCCESS'],
        ]);

        $this->service->payOtp('TX-1', $this->request()->withDccDecision(DccDecision::ACCEPT));

        $body = json_decode((string) $this->http->lastRequest()->getBody(), true);
        self::assertSame('accept', $body['dccDecision']);
    }

    public function testErrorAtHttp200Throws(): void
    {
        $this->http->queueJson(200, [
            'success' => false,
            'status' => 'error',
            'message' => 'DCC_OFFER_EXPIRED',
        ]);

        try {
            $this->service->payOtp('TX-1', $this->request());
            self::fail('Expected CardPaymentException');
        } catch (CardPaymentException $exception) {
            self::assertSame('DCC_OFFER_EXPIRED', $exception->getMessage());
            self::assertSame('DCC_OFFER_EXPIRED', $exception->getErrorCode());
            self::assertSame(200, $exception->getHttpStatus());
        }
    }

    public function testPreAuthUsesOwnPath(): void
    {
        $this->http->queueJson(200, [
            'success' => true,
            'status' => 'success',
            'message' => ['redirectText' => '', 'redirectType' => 'SUCCESS'],
        ]);

        $this->service->preAuth('TX-1', $this->request());

        self::assertSame(
            'https://api-payments.dpay.pl/api/v1_0/cards/payment/TX-1/pay/card-pre-auth',
            $this->http->lastRequest()->getUrl()
        );
    }

    public function testCaptureSendsAmount(): void
    {
        $this->http->queueJson(200, [
            'success' => true,
            'status' => 'success',
            'message' => ['redirectType' => 'SUCCESS'],
        ]);

        $result = $this->service->capture('TX-1', \DPay\Money::pln(5999));

        self::assertTrue($result->isSuccess());
        $request = $this->http->lastRequest();
        self::assertSame('https://api-payments.dpay.pl/api/v1_0/cards/payment/TX-1/capture', $request->getUrl());
        self::assertSame('{"amount":59.99}', $request->getBody());
    }

    public function testCancelWithoutAmountSendsEmptyObject(): void
    {
        $this->http->queueJson(200, [
            'success' => true,
            'status' => 'success',
            'message' => ['redirectType' => 'SUCCESS'],
        ]);

        $this->service->cancel('TX-1');

        $request = $this->http->lastRequest();
        self::assertSame('https://api-payments.dpay.pl/api/v1_0/cards/payment/TX-1/cancellation', $request->getUrl());
        self::assertSame('{}', $request->getBody());
    }

    public function testCaptureErrorThrows(): void
    {
        $this->http->queueJson(200, [
            'success' => false,
            'status' => 'error',
            'message' => 'Capture amount exceeds authorized amount',
        ]);

        $this->expectException(CardPaymentException::class);
        $this->service->capture('TX-1', \DPay\Money::pln(999999));
    }

    public function testGooglePay(): void
    {
        $this->http->queueJson(200, [
            'success' => true,
            'status' => 'success',
            'message' => ['redirectType' => 'SUCCESS'],
        ]);

        $deviceInfo = DeviceInfo::create(
            'text/html',
            'pl-PL',
            24,
            1200,
            1920,
            -60,
            'Mozilla/5.0',
            'Win32',
            '10.10,10.10',
            'device-1',
            'Netscape'
        );
        $googlePay = \DPay\Card\GooglePayRequest::create('TOKEN123', $deviceInfo)
            ->withEmail('client@example.com');

        $this->service->googlePay('TX-1', $googlePay);

        $body = json_decode((string) $this->http->lastRequest()->getBody(), true);
        self::assertSame('GOOGLE_PAY', $body['xPayType']);
        self::assertSame('TOKEN123', $body['xPayToken']);
        self::assertSame('client@example.com', $body['email']);
    }

    public function testApplePayInit(): void
    {
        $this->http->queueJson(200, [
            'success' => true,
            'status' => 'success',
            'message' => ['redirectType' => 'SUCCESS'],
        ]);

        $applePay = \DPay\Card\ApplePayRequest::init(DeviceInfo::create(
            'text/html',
            'pl-PL',
            24,
            1200,
            1920,
            -60,
            'Mozilla/5.0',
            'Win32',
            '10.10,10.10',
            'device-1',
            'Netscape'
        ));

        $this->service->applePay('TX-1', $applePay);

        $body = json_decode((string) $this->http->lastRequest()->getBody(), true);
        self::assertSame('APPLE_PAY_INIT', $body['xPayType']);
        self::assertArrayNotHasKey('xPayToken', $body);
    }
}

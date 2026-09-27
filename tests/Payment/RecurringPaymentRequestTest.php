<?php

declare(strict_types=1);

namespace DPay\Tests\Payment;

use DPay\Config;
use DPay\Internal\ApiRequestor;
use DPay\Money;
use DPay\Payment\PaymentService;
use DPay\Payment\RegisterPaymentRequest;
use DPay\Payment\ReturnUrls;
use DPay\Payment\TransactionType;
use DPay\Recurring\RecurringRegistration;
use DPay\Tests\Support\MockHttpClient;
use DPay\Webhook\WebhookTarget;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Registration and charges of recurring payments, optional IPN, webhook target and reference (SDK 0.2.0).
 */
final class RecurringPaymentRequestTest extends TestCase
{
    private MockHttpClient $http;

    private PaymentService $service;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $config = Config::fromArray(['service' => 'sdk-test-service', 'secret_hash' => 'sdk-test-hash-0001']);
        $this->service = new PaymentService(new ApiRequestor($config, $this->http));
    }

    private function urls(?string $ipn = 'https://shop.example/ipn'): ReturnUrls
    {
        return new ReturnUrls('https://shop.example/ok', 'https://shop.example/fail', $ipn);
    }

    private function registration(): RecurringRegistration
    {
        return RecurringRegistration::create('Abonament', RecurringRegistration::MODEL_O, 'https://shop.example/terms')->withAlias('SUB-0001');
    }

    public function testRegistrationSendsTheObjectWithTheBlikCodeWithoutIpn(): void
    {
        $this->http->queueJson(200, ['error' => false, 'msg' => 'Internal processing', 'status' => true, 'transactionId' => 'TX-REG',
            'additionalInfo' => ['recurring_registration' => ['alias' => 'SUB-0001', 'methods' => ['blik']]]]);

        $payment = $this->service->register(
            RegisterPaymentRequest::create(Money::pln(0), TransactionType::TRANSFERS, $this->urls(null))
                ->withBlikCode('777123', 'Mozilla/5.0', '83.238.17.42')
                ->withRecurringRegistration($this->registration())
        );

        $body = $this->http->lastRequestBody();
        self::assertArrayNotHasKey('url_ipn', $body);
        self::assertArrayNotHasKey('register_blik_recurring_alias', $body);
        self::assertSame(
            ['label' => 'Abonament', 'alias' => 'SUB-0001', 'model' => 'O', 'terms_url' => 'https://shop.example/terms'],
            $body['recurring_registration']
        );
        self::assertSame('777123', $body['blik_code']);
        // Bez IPN: pusty ostatni segment, rejestracja bez aliasu w sumie
        self::assertSame('b5dbca75c515bc76094dba4d2853493bf050bfcd47c25bc899361884080f3544', $body['checksum']);
        self::assertSame('SUB-0001', $payment->getRecurringAlias());
        self::assertSame(['blik'], $payment->getRecurringMethods());
    }

    public function testChargeBindsTheAliasInTheChecksum(): void
    {
        $this->http->queueJson(200, ['error' => false, 'msg' => 'Internal processing', 'status' => true, 'transactionId' => 'TX-CHG']);

        $this->service->register(
            RegisterPaymentRequest::create(Money::pln(4999), TransactionType::TRANSFERS, $this->urls())
                ->withRecurringAlias('SUB-0001')
                ->withDescription('Abonament 10/2026')
        );

        $body = $this->http->lastRequestBody();
        self::assertSame('SUB-0001', $body['recurring_alias']);
        self::assertArrayNotHasKey('user_ip', $body);
        // sha256(service|hash|value|url_success|url_fail|url_ipn|recurring_alias)
        self::assertSame('96b80b9bceab99b92228bc8bd793b432b1594484ac45dc2715d2ebd542f3b2aa', $body['checksum']);
    }

    public function testChargeWithoutIpnKeepsTheEmptySegment(): void
    {
        $this->http->queueJson(200, ['error' => false, 'msg' => 'Internal processing', 'status' => true, 'transactionId' => 'TX-CHG']);

        $this->service->register(
            RegisterPaymentRequest::create(Money::pln(4999), TransactionType::TRANSFERS, $this->urls(null))
                ->withRecurringAlias('SUB-0001')
                ->withClientContext('Mozilla/5.0', '83.238.17.42')
        );

        $body = $this->http->lastRequestBody();
        self::assertSame('83.238.17.42', $body['user_ip']);
        self::assertSame('b521018f255bab928187d73802d2dd79c48dda4c6574d1f57e7395cda70a8e5c', $body['checksum']);
    }

    public function testWebhookAndReferenceStayOutOfTheChecksum(): void
    {
        $this->http->queueJson(200, ['error' => false, 'msg' => 'https://secure.dpay.pl/transfer@pay@TX', 'status' => true, 'transactionId' => 'TX']);

        $this->service->register(
            RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())
                ->withWebhook(WebhookTarget::create('https://shop.example/webhooks', ['payment.succeeded', 'payment.failed']))
                ->withReference('order-1234')
        );

        $body = $this->http->lastRequestBody();
        self::assertSame(['url' => 'https://shop.example/webhooks', 'events' => ['payment.succeeded', 'payment.failed']], $body['webhook']);
        self::assertSame('order-1234', $body['reference']);
        self::assertSame('0a163ad60b5d2fd09eacce0032cc4d584e707c457051d2c4365b320dc340bc28', $body['checksum']);
    }

    public function testRegistrationWithoutBlikCodeIsRejectedBeforeSending(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BLIK code');

        RegisterPaymentRequest::create(Money::pln(0), TransactionType::TRANSFERS, $this->urls())
            ->withRecurringRegistration($this->registration())
            ->toBody('sdk-test-service');
    }

    public function testChargeCannotCarryABlikCodeOrAZeroAmount(): void
    {
        foreach ([
            RegisterPaymentRequest::create(Money::pln(4999), TransactionType::TRANSFERS, $this->urls())
                ->withBlikCode('777123', 'Mozilla/5.0', '83.238.17.42')->withRecurringAlias('SUB-0001'),
            RegisterPaymentRequest::create(Money::pln(0), TransactionType::TRANSFERS, $this->urls())->withRecurringAlias('SUB-0001'),
            RegisterPaymentRequest::create(Money::pln(4999), TransactionType::CARD_RECURRING, $this->urls())->withRecurringAlias('SUB-0001'),
        ] as $request) {
            try {
                $request->toBody('sdk-test-service');
                self::fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $exception) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testWebhookOnlyAcceptsEventsOfAPayment(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())
            ->withWebhook(WebhookTarget::create('https://shop.example/webhooks', ['payout.paid']));
    }

    public function testWebhookTargetRequiresHttps(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WebhookTarget::create('http://shop.example/webhooks');
    }
}

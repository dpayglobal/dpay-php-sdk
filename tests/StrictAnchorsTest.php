<?php

declare(strict_types=1);

namespace DPay\Tests;

use DPay\Card\CardData;
use DPay\Card\CardRecurringRegistration;
use DPay\Config;
use DPay\Currency;
use DPay\Exception\SignatureVerificationException;
use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;
use DPay\Money;
use DPay\Payment\InvoiceDetails;
use DPay\Payment\RegisterPaymentRequest;
use DPay\Payment\ReturnUrls;
use DPay\Payment\Transaction;
use DPay\Payment\TransactionType;
use DPay\Recurring\RecurringRegistration;
use DPay\Refund\RefundService;
use DPay\Tests\Support\MockHttpClient;
use DPay\Webhook\EventService;
use DPay\Webhook\WebhookVerifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Every pattern of the SDK is anchored at the very end of the string: PHP `$` without the D modifier also matches
 * before a trailing "\n", which let values such as "30\n" or "X-Id\n" through.
 */
final class StrictAnchorsTest extends TestCase
{
    /**
     * @dataProvider rejectedWithTrailingNewline
     *
     * @param callable(): mixed $call
     */
    public function testTrailingNewlineIsRejected(callable $call, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $call();
    }

    /**
     * @return array<string, array{callable(): mixed, string}>
     */
    public static function rejectedWithTrailingNewline(): array
    {
        $api = static function (): ApiRequestor {
            return new ApiRequestor(Config::fromArray(['service' => 'MyShop', 'secret_hash' => 'secret123']), new MockHttpClient());
        };
        $request = static function (): RegisterPaymentRequest {
            return RegisterPaymentRequest::create(
                Money::pln(1000),
                TransactionType::TRANSFERS,
                new ReturnUrls('https://shop.example/ok', 'https://shop.example/fail', 'https://shop.example/ipn')
            );
        };

        return [
            'extra header name' => [
                static function () use ($api) {
                    return $api()->sendRaw('POST', BaseUrls::PANEL, '/x', [], ["X-Id\n" => '1']);
                },
                'Invalid request header "X-Id' . "\n" . '"',
            ],
            'refund message id' => [
                static function () use ($api) {
                    return (new RefundService($api()))->create('30D9493D-1D73-3FBD-A5D4-633723CC7A68', null, null, null, "RET\n");
                },
                'Refund message ID must be non-empty printable ASCII',
            ],
            'card number' => [
                static function () {
                    return new CardData("4111111111111111\n", '123', '12/30');
                },
                'Card number must be 12-19 digits',
            ],
            'cvv' => [
                static function () {
                    return new CardData('4111111111111111', "123\n", '12/30');
                },
                'CVV must be 3-4 digits',
            ],
            'expiry' => [
                static function () {
                    return new CardData('4111111111111111', '123', "12/30\n");
                },
                'Expiry must be in MM/YY format',
            ],
            'card recurring date' => [
                static function () {
                    return CardRecurringRegistration::create('Abonament')->withInitDate("2026-12-01\n");
                },
                'Date "2026-12-01' . "\n" . '" must be in YYYY-MM-DD format',
            ],
            'money decimal' => [
                static function () {
                    return Money::fromDecimal("10.00\n", Currency::PLN);
                },
                'Invalid money amount "10.00' . "\n" . '"',
            ],
            'invoice due date' => [
                static function () {
                    return InvoiceDetails::create()->withPaymentDueDate("2026-12-01\n");
                },
                'Payment due date must be in YYYY-MM-DD format',
            ],
            'partner platform' => [
                static function () use ($request) {
                    return $request()->withPartnerPlatform("SHOPER\n");
                },
                'Partner platform must match ^[A-Z0-9]{1,64}$',
            ],
            'blik code' => [
                static function () use ($request) {
                    return $request()->withBlikCode("123456\n", 'Mozilla/5.0', '203.0.113.10');
                },
                'BLIK code must be exactly 6 digits',
            ],
            'recurring frequency' => [
                static function () {
                    return RecurringRegistration::create('Abonament', RecurringRegistration::MODEL_O, 'https://shop.example/terms')->withFrequency("1M\n");
                },
                'Invalid recurring frequency "1M' . "\n" . '"',
            ],
            'recurring date' => [
                static function () {
                    return RecurringRegistration::create('Abonament', RecurringRegistration::MODEL_O, 'https://shop.example/terms')->withInitDate("2026-12-01\n");
                },
                'Date "2026-12-01' . "\n" . '" must be in YYYY-MM-DD format',
            ],
            'events starting_after' => [
                static function () use ($api) {
                    return (new EventService($api()))->list(['starting_after' => "evt_01k6a8q2m4pz7h8c3v5n9t2x6y\n"]);
                },
                'starting_after must be an event id (evt_...)',
            ],
        ];
    }

    public function testValuesThatAreNotValidated(): void
    {
        self::assertFalse(Currency::isValid("PLN\n"));
        self::assertNull(Money::tryFromApiNumber("10.00\n", Currency::PLN));
    }

    public function testTransactionIntegerFieldWithTrailingNewlineIsNull(): void
    {
        $transaction = Transaction::fromArray(['transaction' => ['minimal_fee' => "30\n", 'permanent_fee' => '30']]);

        self::assertNull($transaction->getMinimalFee());
        self::assertSame(30, $transaction->getPermanentFee());
    }

    public function testWebhookTimestampWithTrailingNewlineIsRejected(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Invalid webhook-timestamp header');
        WebhookVerifier::verify('{}', [
            'webhook-id' => 'evt_01k6a8q2m4pz7h8c3v5n9t2x6y',
            'webhook-timestamp' => "1790503500\n",
            'webhook-signature' => 'v1,x',
        ], 'whsec_ZHBheS1zZGstc3ludGhldGljLXRlc3Qtc2VjcmV0ISE=', 300, 1790503500);
    }
}

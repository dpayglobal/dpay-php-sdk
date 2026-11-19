<?php

declare(strict_types=1);

namespace DPay\Tests\Payment;

use DPay\Currency;
use DPay\Money;
use DPay\Payment\DeviceInfo;
use DPay\Payment\Payer;
use DPay\Payment\RegisterPaymentRequest;
use DPay\Payment\ReturnUrls;
use DPay\Payment\TransactionType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RegisterPaymentRequestTest extends TestCase
{
    private function urls(): ReturnUrls
    {
        return new ReturnUrls('https://shop.example/ok', 'https://shop.example/fail', 'https://shop.example/ipn');
    }

    public function testMinimalBody(): void
    {
        $request = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls());

        $body = $request->toBody('MyShop');

        self::assertSame([
            'service' => 'MyShop',
            'value' => '10.00',
            'transactionType' => 'transfers',
            'url_success' => 'https://shop.example/ok',
            'url_fail' => 'https://shop.example/fail',
            'url_ipn' => 'https://shop.example/ipn',
        ], $body);
    }

    public function testFullBodyKeepsOrderAndSerialization(): void
    {
        $request = RegisterPaymentRequest::create(Money::pln(1050), TransactionType::TRANSFERS, $this->urls())
            ->withDescription('Zamowienie #1234')
            ->withCustom('order-1234')
            ->withPayer(Payer::create()->withEmail('client@example.com')->withName('Jan', 'Kowalski'))
            ->withAcceptTos(true)
            ->withChannel('24900005')
            ->withCreditCard(false)
            ->withNoBanks(true);

        $body = $request->toBody('MyShop');

        self::assertSame('10.50', $body['value']);
        self::assertSame('Zamowienie #1234', $body['description']);
        self::assertSame('client@example.com', $body['email']);
        self::assertSame('Jan', $body['client_name']);
        self::assertSame('Kowalski', $body['client_surname']);
        self::assertTrue($body['accept_tos']);
        self::assertSame(0, $body['creditcard']);
        self::assertSame(1, $body['nobanks']);
        self::assertSame(
            ['service', 'value', 'transactionType', 'url_success', 'url_fail', 'url_ipn',
                'description', 'custom', 'email', 'client_name', 'client_surname', 'accept_tos',
                'channel', 'creditcard', 'nobanks'],
            array_keys($body)
        );
    }

    public function testPhoneNumberSetsCurrency(): void
    {
        $body = $this->mbWay()->toBody('MyShop');

        self::assertSame('+351912345678', $body['phone_number']);
        self::assertSame('EUR', $body['currency_code']);
        self::assertSame('373', $body['channel']);
        $deviceInfo = $body['device_info'];
        self::assertIsArray($deviceInfo);
        self::assertSame('false', $deviceInfo['browserJavaEnabled']);
    }

    public function testNonPlnAmountSendsItsCurrency(): void
    {
        $body = RegisterPaymentRequest::create(Money::of(49900, Currency::CZK), TransactionType::TRANSFERS, $this->urls())->toBody('MyShop');

        self::assertSame('499.00', $body['value']);
        self::assertSame('CZK', $body['currency_code']);
        self::assertSame(
            ['service', 'value', 'transactionType', 'url_success', 'url_fail', 'url_ipn', 'currency_code'],
            array_keys($body)
        );
    }

    public function testPlnAmountSendsNoCurrencyUnlessAskedTo(): void
    {
        $plain = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())->toBody('MyShop');
        $explicit = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())
            ->withCurrencyCode(Currency::PLN)
            ->toBody('MyShop');

        self::assertArrayNotHasKey('currency_code', $plain);
        self::assertSame('PLN', $explicit['currency_code']);
    }

    public function testCurrencyCodeMustMatchTheAmount(): void
    {
        $request = RegisterPaymentRequest::create(Money::pln(500), TransactionType::TRANSFERS, $this->urls());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency code "EUR" does not match the amount currency "PLN"');
        $request->withCurrencyCode(Currency::EUR);
    }

    public function testPhoneNumberCurrencyMustMatchTheAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RegisterPaymentRequest::create(Money::pln(500), TransactionType::MB_WAY_DIRECT, $this->urls())
            ->withPhoneNumber('+351912345678', Currency::EUR);
    }

    /**
     * @dataProvider invalidPhoneNumbers
     */
    public function testPhoneNumberLength(string $phoneNumber): void
    {
        $this->expectException(InvalidArgumentException::class);
        RegisterPaymentRequest::create(Money::of(500, Currency::EUR), TransactionType::MB_WAY_DIRECT, $this->urls())
            ->withPhoneNumber($phoneNumber, Currency::EUR);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPhoneNumbers(): array
    {
        return ['8 characters' => ['+3519123'], '16 characters' => ['+351912345678901']];
    }

    public function testNewCurrencyConstants(): void
    {
        self::assertSame('USD', Currency::USD);
        self::assertSame('RON', Currency::RON);
        self::assertSame('RON', RegisterPaymentRequest::create(Money::of(100, Currency::RON), TransactionType::TRANSFERS, $this->urls())->toBody('S')['currency_code']);
    }

    public function testMbWayRequiresAChannel(): void
    {
        $request = RegisterPaymentRequest::create(Money::of(500, Currency::EUR), TransactionType::MB_WAY_DIRECT, $this->urls())
            ->withPhoneNumber('+351912345678', Currency::EUR)
            ->withPayer(Payer::create()->withEmail('client@example.com')->withName('Joao', 'Silva'))
            ->withAcceptTos(true)
            ->withDeviceInfo($this->deviceInfo(true));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a channel');
        $request->toBody('MyShop');
    }

    public function testMbWayRequiresBrowserJavaEnabled(): void
    {
        $request = $this->mbWay()->withDeviceInfo($this->deviceInfo(null));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('browserJavaEnabled');
        $request->toBody('MyShop');
    }

    public function testMbWayRequiresDeviceInfo(): void
    {
        $request = RegisterPaymentRequest::create(Money::of(500, Currency::EUR), TransactionType::MB_WAY_DIRECT, $this->urls())
            ->withPhoneNumber('+351912345678', Currency::EUR)
            ->withChannel('373')
            ->withPayer(Payer::create()->withEmail('client@example.com')->withName('Joao', 'Silva'))
            ->withAcceptTos(true);

        $this->expectException(InvalidArgumentException::class);
        $request->toBody('MyShop');
    }

    public function testChannelRequiresPayerAndTerms(): void
    {
        $withoutPayer = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())
            ->withChannel('24900005')
            ->withAcceptTos(true);
        try {
            $withoutPayer->toBody('MyShop');
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('email, first name and last name', $exception->getMessage());
        }

        $withoutName = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())
            ->withChannel('24900005')
            ->withPayer(Payer::create()->withEmail('client@example.com'))
            ->withAcceptTos(true);
        try {
            $withoutName->toBody('MyShop');
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('first name', $exception->getMessage());
        }

        $withoutTerms = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())
            ->withChannel('24900005')
            ->withPayer(Payer::create()->withEmail('client@example.com')->withName('Jan', 'Kowalski'));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('withAcceptTos');
        $withoutTerms->toBody('MyShop');
    }

    public function testDeclinedTermsStillCountAsSent(): void
    {
        $body = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())
            ->withChannel('24900005')
            ->withPayer(Payer::create()->withEmail('client@example.com')->withName('Jan', 'Kowalski'))
            ->withAcceptTos(false)
            ->toBody('MyShop');

        self::assertFalse($body['accept_tos']);
    }

    /**
     * @dataProvider forbiddenChannels
     */
    public function testForbiddenDirectChannels(string $channel): void
    {
        $this->expectException(InvalidArgumentException::class);
        RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())->withChannel($channel);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function forbiddenChannels(): array
    {
        return ['tester' => ['tester'], 'paypal' => ['paypal'], 'installment' => ['installment'], 'paysafecard' => ['paysafecard'], 'empty' => ['']];
    }

    public function testAliasIpnUrlIsAtMost500Characters(): void
    {
        $url = 'https://shop.example/' . str_repeat('a', 479);
        self::assertSame(500, strlen($url));
        $body = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())->withAliasIpnUrl($url)->toBody('S');
        self::assertSame($url, $body['alias_ipn_url']);

        $this->expectException(InvalidArgumentException::class);
        RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())->withAliasIpnUrl($url . 'a');
    }

    /**
     * @dataProvider efakturaConflicts
     */
    public function testEfakturaCannotBeCombinedWithAliasesOrRecurring(callable $configure, string $field): void
    {
        $request = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())->withEfaktura();
        $configure($request);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('efaktura cannot be combined with ' . $field);
        $request->toBody('MyShop');
    }

    /**
     * @return array<string, array{callable(RegisterPaymentRequest): void, string}>
     */
    public static function efakturaConflicts(): array
    {
        return [
            'blik_code' => [static function (RegisterPaymentRequest $request): void {
                $request->withBlikCode('777123', 'UA', '1.2.3.4');
            }, 'blik_code'],
            'blik_alias' => [static function (RegisterPaymentRequest $request): void {
                $request->withBlikAlias('DPAY.UID.1.a', 'UA', '1.2.3.4');
            }, 'blik_alias'],
            'recurring_alias' => [static function (RegisterPaymentRequest $request): void {
                $request->withRecurringAlias('SUB-0001');
            }, 'recurring_alias'],
        ];
    }

    private function mbWay(): RegisterPaymentRequest
    {
        return RegisterPaymentRequest::create(Money::of(500, Currency::EUR), TransactionType::MB_WAY_DIRECT, $this->urls())
            ->withPhoneNumber('+351912345678', Currency::EUR)
            ->withChannel('373')
            ->withPayer(Payer::create()->withEmail('client@example.com')->withName('Joao', 'Silva'))
            ->withAcceptTos(true)
            ->withDeviceInfo($this->deviceInfo(false));
    }

    private function deviceInfo(?bool $javaEnabled): DeviceInfo
    {
        $deviceInfo = DeviceInfo::create('text/html', 'pt-PT', 24, 1200, 1920, -60, 'Mozilla/5.0', 'Win32', '38.72,-9.14', 'device-1', 'Netscape');

        return $javaEnabled === null ? $deviceInfo : $deviceInfo->withBrowserJavaEnabled($javaEnabled);
    }

    public function testRejectsUnknownTransactionType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RegisterPaymentRequest::create(Money::pln(1000), 'cash', $this->urls());
    }

    public function testReturnUrlsValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ReturnUrls('not-a-url', 'https://shop.example/fail', 'https://shop.example/ipn');
    }

    public function testPayerEmailValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Payer::create()->withEmail('not-an-email');
    }

    public function testPartnerPlatformValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())
            ->withPartnerPlatform('lower case');
    }

    public function testBlikZeroFields(): void
    {
        $request = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())
            ->withBlikCode('777123', 'Mozilla/5.0', '203.0.113.7');

        $body = $request->toBody('MyShop');

        self::assertSame('Mozilla/5.0', $body['user_agent']);
        self::assertSame('203.0.113.7', $body['user_ip']);
        self::assertSame('777123', $body['blik_code']);
    }

    public function testBlikAliasExcludesBlikCode(): void
    {
        $request = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())
            ->withBlikCode('777123', 'UA', '1.2.3.4');

        $this->expectException(InvalidArgumentException::class);
        $request->withBlikAlias('DPAY.UID.1.a', 'UA', '1.2.3.4');
    }

    public function testRegisterBlikAliasSerializesObject(): void
    {
        $request = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())
            ->withBlikCode('777123', 'UA', '1.2.3.4')
            ->withRegisterBlikAlias(new \DPay\Blik\BlikAliasRegistration('Moj sklep'));

        $body = $request->toBody('MyShop');

        self::assertSame(['label' => 'Moj sklep', 'type' => 'UID'], $body['register_blik_alias']);
    }

    public function testBlikCodeMustBeSixDigits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RegisterPaymentRequest::create(Money::pln(1000), TransactionType::TRANSFERS, $this->urls())
            ->withBlikCode('12345', 'UA', '1.2.3.4');
    }

    public function testCardRecurringRegistration(): void
    {
        $request = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::CARD_RECURRING, $this->urls())
            ->withCardRecurring(\DPay\Card\CardRecurringRegistration::create('Subskrypcja'));

        $body = $request->toBody('MyShop');

        self::assertSame(['label' => 'Subskrypcja'], $body['register_card_recurring']);
    }

    public function testCardRecurringCharge(): void
    {
        $request = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::CARD_RECURRING, $this->urls())
            ->withCardRecurringAlias('CARD-ALIAS-1')
            ->withAuthorizeOnly(true)
            ->withCardRecurringOperation(\DPay\Card\CardRecurringOperation::CHARGE);

        $body = $request->toBody('MyShop');

        self::assertSame('CARD-ALIAS-1', $body['card_recurring_alias']);
        self::assertTrue($body['authorize_only']);
        self::assertSame('charge', $body['card_recurring_operation']);
    }

    public function testCardRecurringMutualExclusion(): void
    {
        $request = RegisterPaymentRequest::create(Money::pln(1000), TransactionType::CARD_RECURRING, $this->urls())
            ->withCardRecurring(\DPay\Card\CardRecurringRegistration::create('X'));

        $this->expectException(InvalidArgumentException::class);
        $request->withCardRecurringAlias('CARD-ALIAS-1');
    }

    public function testPayoutInstruction(): void
    {
        $payout = \DPay\Payment\PayoutInstruction::create([
            new \DPay\Payment\PayoutPosition('PL61109010140000071219812874', 'FV 2026/06/001', Money::pln(25000)),
        ], \DPay\Payment\PayoutFeeMode::GROSS);

        $body = RegisterPaymentRequest::create(Money::pln(30000), TransactionType::TRANSFERS, $this->urls())
            ->withPayout($payout)
            ->toBody('MyShop');

        $payoutBody = $body['payout'];
        self::assertIsArray($payoutBody);
        self::assertSame('gross', $payoutBody['fee_mode']);
        self::assertIsArray($payoutBody['positions']);
        $position = $payoutBody['positions'][0];
        self::assertIsArray($position);
        self::assertSame('PL61109010140000071219812874', $position['iban']);
        self::assertSame(250.0, $position['amount']);
    }

    public function testEfakturaWithInvoice(): void
    {
        $invoice = \DPay\Payment\InvoiceDetails::create()
            ->withPayerNip('5252248481')
            ->withInvoiceNumber('FV/2026/07/500')
            ->withVatAmount(Money::pln(11500));

        $body = RegisterPaymentRequest::create(Money::pln(61500), TransactionType::TRANSFERS, $this->urls())
            ->withEfaktura($invoice)
            ->toBody('MyShop');

        self::assertTrue($body['efaktura']);
        $invoiceBody = $body['invoice'];
        self::assertIsArray($invoiceBody);
        self::assertSame('5252248481', $invoiceBody['payer_nip']);
        self::assertSame(11500, $invoiceBody['vat_amount']);
    }

    public function testEfakturaRequiresTransfers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RegisterPaymentRequest::create(Money::pln(1000), TransactionType::CARD_RECURRING, $this->urls())
            ->withEfaktura();
    }

    public function testPayoutRequiresPositions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \DPay\Payment\PayoutInstruction::create([]);
    }

    public function testPayoutPositionRequiresAValidPolishIban(): void
    {
        $spaced = new \DPay\Payment\PayoutPosition('pl61 1090 1014 0000 0712 1981 2874', 'FV 1', Money::pln(100));
        self::assertSame('pl61 1090 1014 0000 0712 1981 2874', $spaced->toArray()['iban']);

        foreach (['PL61109010140000071219812875', 'DE89370400440532013000', '61109010140000071219812874', ''] as $iban) {
            try {
                new \DPay\Payment\PayoutPosition($iban, 'FV 1', Money::pln(100));
                self::fail('Expected InvalidArgumentException for ' . $iban);
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('valid Polish IBAN', $exception->getMessage());
            }
        }
    }

    public function testPayoutPositionRequiresAPositiveAmount(): void
    {
        foreach ([Money::pln(0), Money::pln(-100)] as $amount) {
            try {
                new \DPay\Payment\PayoutPosition('PL61109010140000071219812874', 'FV 1', $amount);
                self::fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Payout amount must be above 0', $exception->getMessage());
            }
        }
    }

    public function testBlikAliasLabelIsAtMost35Characters(): void
    {
        self::assertSame(['label' => str_repeat('a', 35), 'type' => 'UID'], (new \DPay\Blik\BlikAliasRegistration(str_repeat('a', 35)))->toArray());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Alias label must be 1-35 characters');
        new \DPay\Blik\BlikAliasRegistration(str_repeat('a', 36));
    }
}

<?php

declare(strict_types=1);

namespace DPay\Tests\Payment;

use DPay\Money;
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
        $request = RegisterPaymentRequest::create(Money::of(500, 'EUR'), TransactionType::MB_WAY_DIRECT, $this->urls())
            ->withPhoneNumber('+351912345678', 'EUR');

        $body = $request->toBody('MyShop');

        self::assertSame('+351912345678', $body['phone_number']);
        self::assertSame('EUR', $body['currency_code']);
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
}

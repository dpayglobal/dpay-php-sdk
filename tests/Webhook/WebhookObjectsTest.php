<?php

declare(strict_types=1);

namespace DPay\Tests\Webhook;

use DPay\Money;
use DPay\Webhook\DeclineCode;
use DPay\Webhook\FailureCategory;
use DPay\Webhook\PaymentObject;
use DPay\Webhook\PayoutObject;
use DPay\Webhook\RecurringPaymentObject;
use DPay\Webhook\RefundObject;
use DPay\Webhook\WebhookEvent;
use PHPUnit\Framework\TestCase;

/**
 * Typed `data.object` models of webhook events (payloads as in the webhook documentation and the API object builders).
 */
final class WebhookObjectsTest extends TestCase
{
    /**
     * @param array<string, mixed> $object
     */
    private function event(string $type, array $object): WebhookEvent
    {
        return WebhookEvent::fromArray([
            'id' => 'evt_01k6a8q2m4pz7h8c3v5n9t2x6y',
            'type' => $type,
            'api_version' => '2026-10-01',
            'created' => '2026-09-27T10:05:00Z',
            'livemode' => true,
            'service' => 'abc123',
            'data' => ['object' => $object],
        ]);
    }

    public function testPaymentSucceeded(): void
    {
        $event = $this->event('payment.succeeded', [
            'object' => 'payment',
            'id' => '8F2A11C4-5B6D-4E7F-8A9B-0C1D2E3F4A5B',
            'status' => 'succeeded',
            'amount' => 14900,
            'currency' => 'PLN',
            'amount_refunded' => 0,
            'amount_captured' => 0,
            'description' => 'Order 2026/09/123',
            'created' => '2026-09-27T10:03:12Z',
            'paid_at' => '2026-09-27T10:05:00Z',
            'payment_method' => ['type' => 'blik', 'blik' => ['flow' => 'code']],
            'failure' => null,
            'recurring' => null,
            'references' => ['merchant' => '2026/09/123', 'provider' => '1234567890'],
            'custom' => 'order-789',
        ]);

        $payment = $event->getPayment();
        self::assertNotNull($payment);
        self::assertNull($event->getRefund());
        self::assertNull($event->getRecurringPayment());
        self::assertNull($event->getPayout());
        self::assertSame('8F2A11C4-5B6D-4E7F-8A9B-0C1D2E3F4A5B', $payment->getId());
        self::assertSame(PaymentObject::STATUS_SUCCEEDED, $payment->getStatus());
        self::assertTrue($payment->getAmount()->equals(Money::pln(14900)));
        self::assertTrue($payment->getAmountRefunded()->equals(Money::pln(0)));
        self::assertSame(0, $payment->getAmountCaptured()->getMinor());
        self::assertSame('Order 2026/09/123', $payment->getDescription());
        self::assertSame('2026-09-27T10:03:12Z', $payment->getCreated());
        self::assertSame('2026-09-27T10:05:00Z', $payment->getPaidAt());
        self::assertSame('blik', $payment->getPaymentMethodType());
        self::assertSame(['type' => 'blik', 'blik' => ['flow' => 'code']], $payment->getPaymentMethod());
        self::assertNull($payment->getFailure());
        self::assertFalse($payment->isRecurring());
        self::assertNull($payment->getRecurringAlias());
        self::assertSame('2026/09/123', $payment->getMerchantReference());
        self::assertSame('1234567890', $payment->getProviderReference());
        self::assertSame('order-789', $payment->getCustom());
    }

    public function testPaymentFailedRecurringChargeInEuro(): void
    {
        $payment = $this->event('payment.failed', [
            'object' => 'payment',
            'id' => 'P-2',
            'status' => 'failed',
            'amount' => 4999,
            'currency' => 'EUR',
            'amount_refunded' => 0,
            'amount_captured' => 0,
            'description' => null,
            'created' => '2026-10-01T08:00:00Z',
            'paid_at' => null,
            'payment_method' => null,
            'failure' => [
                'code' => 'insufficient_funds',
                'message' => 'Insufficient funds',
                'category' => 'customer',
                'retryable' => true,
                'provider_code' => 'INSUFFICIENT_FUNDS',
            ],
            'recurring' => ['alias' => 'SUB-0001', 'registration' => 'P-1', 'sequence' => 'subsequent'],
            'references' => ['merchant' => null, 'provider' => null],
            'custom' => null,
        ])->getPayment();

        self::assertNotNull($payment);
        self::assertSame(PaymentObject::STATUS_FAILED, $payment->getStatus());
        self::assertTrue($payment->getAmount()->equals(Money::of(4999, 'EUR')));
        self::assertNull($payment->getPaidAt());
        self::assertNull($payment->getPaymentMethodType());
        self::assertNull($payment->getMerchantReference());
        self::assertTrue($payment->isRecurring());
        self::assertSame('SUB-0001', $payment->getRecurringAlias());
        self::assertSame('P-1', $payment->getRecurringRegistration());
        self::assertSame(PaymentObject::SEQUENCE_SUBSEQUENT, $payment->getRecurringSequence());
        $failure = $payment->getFailure();
        self::assertNotNull($failure);
        self::assertSame(DeclineCode::INSUFFICIENT_FUNDS, $failure->getCode());
        self::assertSame('Insufficient funds', $failure->getMessage());
        self::assertSame(FailureCategory::CUSTOMER, $failure->getCategory());
        self::assertTrue($failure->isRetryable());
        self::assertSame('INSUFFICIENT_FUNDS', $failure->getProviderCode());
    }

    public function testRefundFailed(): void
    {
        $refund = $this->event('refund.failed', [
            'object' => 'refund',
            'id' => null,
            'payment' => '8F2A11C4-5B6D-4E7F-8A9B-0C1D2E3F4A5B',
            'status' => 'failed',
            'amount' => 2000,
            'currency' => 'PLN',
            'created' => '2026-10-01T08:00:00Z',
            'succeeded_at' => null,
            'failure' => ['code' => 'refund_window_expired', 'message' => 'The refund period has expired', 'category' => 'merchant', 'retryable' => false, 'provider_code' => null],
        ])->getRefund();

        self::assertNotNull($refund);
        self::assertNull($refund->getId());
        self::assertSame('8F2A11C4-5B6D-4E7F-8A9B-0C1D2E3F4A5B', $refund->getPayment());
        self::assertSame(RefundObject::STATUS_FAILED, $refund->getStatus());
        self::assertSame(2000, $refund->getAmount()->getMinor());
        self::assertNull($refund->getSucceededAt());
        $failure = $refund->getFailure();
        self::assertNotNull($failure);
        self::assertSame(DeclineCode::REFUND_WINDOW_EXPIRED, $failure->getCode());
        self::assertSame(FailureCategory::MERCHANT, $failure->getCategory());
        self::assertFalse($failure->isRetryable());
        self::assertNull($failure->getProviderCode());
    }

    public function testRecurringPaymentCanceled(): void
    {
        $recurring = $this->event('recurring_payment.canceled', [
            'object' => 'recurring_payment',
            'id' => 'P-1',
            'alias' => 'SUB-0001',
            'status' => 'canceled',
            'canceled_by' => 'customer',
            'payment_method' => ['type' => 'blik', 'blik' => ['flow' => 'recurring']],
            'terms' => [
                'model' => 'A',
                'frequency' => '1M',
                'limit_amount' => 5999,
                'total_limit_amount' => 71988,
                'starts_on' => '2026-11-01',
                'expires_on' => '2027-09-30',
            ],
            'created' => '2026-09-26T10:00:00Z',
        ])->getRecurringPayment();

        self::assertNotNull($recurring);
        self::assertSame('P-1', $recurring->getId());
        self::assertSame('SUB-0001', $recurring->getAlias());
        self::assertSame(RecurringPaymentObject::STATUS_CANCELED, $recurring->getStatus());
        self::assertSame(RecurringPaymentObject::CANCELED_BY_CUSTOMER, $recurring->getCanceledBy());
        self::assertSame('blik', $recurring->getPaymentMethodType());
        self::assertSame('A', $recurring->getModel());
        self::assertSame('1M', $recurring->getFrequency());
        self::assertSame(5999, $recurring->getLimitAmount());
        self::assertSame(71988, $recurring->getTotalLimitAmount());
        self::assertSame('2026-11-01', $recurring->getStartsOn());
        self::assertSame('2027-09-30', $recurring->getExpiresOn());
        self::assertSame('2026-09-26T10:00:00Z', $recurring->getCreated());
    }

    public function testRecurringPaymentModelOHasNoLimits(): void
    {
        $recurring = $this->event('recurring_payment.activated', [
            'object' => 'recurring_payment',
            'id' => 'P-1',
            'alias' => 'SUB-0002',
            'status' => 'active',
            'canceled_by' => null,
            'payment_method' => null,
            'terms' => ['model' => 'O', 'frequency' => null, 'limit_amount' => null, 'total_limit_amount' => null, 'starts_on' => null, 'expires_on' => null],
            'created' => '2026-09-26T10:00:00Z',
        ])->getRecurringPayment();

        self::assertNotNull($recurring);
        self::assertSame(RecurringPaymentObject::STATUS_ACTIVE, $recurring->getStatus());
        self::assertNull($recurring->getCanceledBy());
        self::assertNull($recurring->getLimitAmount());
        self::assertNull($recurring->getFrequency());
    }

    public function testPayoutFailed(): void
    {
        $payout = $this->event('payout.failed', [
            'object' => 'payout',
            'id' => '12345',
            'status' => 'failed',
            'amount' => 2715,
            'fee' => 306,
            'currency' => 'PLN',
            'bank_account' => ['last4' => '2874'],
            'created' => '2026-08-01T08:00:00Z',
            'paid_at' => null,
            'failure' => ['code' => 'payout_failed', 'message' => 'The payout could not be completed', 'category' => 'merchant', 'retryable' => false, 'provider_code' => 'RETURNED'],
        ])->getPayout();

        self::assertNotNull($payout);
        self::assertSame('12345', $payout->getId());
        self::assertSame(PayoutObject::STATUS_FAILED, $payout->getStatus());
        self::assertSame(2715, $payout->getAmount()->getMinor());
        self::assertSame(306, $payout->getFee()->getMinor());
        self::assertSame('2874', $payout->getBankAccountLast4());
        self::assertNull($payout->getPaidAt());
        $failure = $payout->getFailure();
        self::assertNotNull($failure);
        self::assertSame(DeclineCode::PAYOUT_FAILED, $failure->getCode());
        self::assertSame('RETURNED', $failure->getProviderCode());
    }

    public function testTestEventHasNoTypedObject(): void
    {
        $event = $this->event('webhook.test', ['object' => 'webhook_endpoint', 'id' => 'we_1']);

        self::assertNull($event->getPayment());
        self::assertNull($event->getPayout());
        self::assertSame('webhook_endpoint', $event->getObjectType());
    }

    public function testMissingFieldsFallBack(): void
    {
        $payment = PaymentObject::fromArray(['object' => 'payment', 'currency' => 'xx', 'failure' => ['code' => '']]);

        self::assertSame('', $payment->getId());
        self::assertSame('PLN', $payment->getCurrency());
        self::assertSame(0, $payment->getAmount()->getMinor());
        $failure = $payment->getFailure();
        self::assertNotNull($failure);
        self::assertSame(DeclineCode::GENERIC_DECLINE, $failure->getCode());
        self::assertFalse($failure->isRetryable());
        self::assertNull($failure->getCategory());
    }

    public function testDeclineCatalog(): void
    {
        self::assertCount(22, DeclineCode::ALL);
        self::assertSame(DeclineCode::ALL, array_values(array_unique(DeclineCode::ALL)));
        self::assertSame(['customer', 'merchant', 'risk', 'system'], FailureCategory::ALL);
    }
}

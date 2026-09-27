<?php

declare(strict_types=1);

namespace DPay\Webhook;

use InvalidArgumentException;

final class WebhookEventType
{
    public const PAYMENT_SUCCEEDED = 'payment.succeeded';
    public const PAYMENT_FAILED = 'payment.failed';
    public const PAYMENT_CAPTURED = 'payment.captured';
    public const REFUND_SUCCEEDED = 'refund.succeeded';
    public const REFUND_FAILED = 'refund.failed';
    public const RECURRING_PAYMENT_ACTIVATED = 'recurring_payment.activated';
    public const RECURRING_PAYMENT_CANCELED = 'recurring_payment.canceled';
    public const RECURRING_PAYMENT_EXPIRED = 'recurring_payment.expired';
    public const RECURRING_PAYMENT_DECLINED = 'recurring_payment.declined';
    public const PAYOUT_PAID = 'payout.paid';
    public const PAYOUT_FAILED = 'payout.failed';
    public const WEBHOOK_TEST = 'webhook.test';

    /** Events a merchant endpoint can subscribe to and the Events API can filter on. */
    public const MERCHANT = [
        self::PAYMENT_SUCCEEDED,
        self::PAYMENT_FAILED,
        self::PAYMENT_CAPTURED,
        self::REFUND_SUCCEEDED,
        self::REFUND_FAILED,
        self::RECURRING_PAYMENT_ACTIVATED,
        self::RECURRING_PAYMENT_CANCELED,
        self::RECURRING_PAYMENT_EXPIRED,
        self::RECURRING_PAYMENT_DECLINED,
        self::PAYOUT_PAID,
        self::PAYOUT_FAILED,
    ];

    /** Events allowed in the `webhook` object of a payment registration. */
    public const PAYMENT_REGISTRATION = [
        self::PAYMENT_SUCCEEDED,
        self::PAYMENT_FAILED,
        self::PAYMENT_CAPTURED,
        self::REFUND_SUCCEEDED,
        self::REFUND_FAILED,
        self::RECURRING_PAYMENT_ACTIVATED,
        self::RECURRING_PAYMENT_CANCELED,
        self::RECURRING_PAYMENT_EXPIRED,
        self::RECURRING_PAYMENT_DECLINED,
    ];

    /** Events allowed in the `webhook` object of a refund. */
    public const REFUND = [
        self::REFUND_SUCCEEDED,
        self::REFUND_FAILED,
    ];

    /** Events allowed in the `webhook` object of a card capture. */
    public const CAPTURE = [
        self::PAYMENT_CAPTURED,
    ];

    /**
     * @param array<int, string> $events
     * @param array<int, string> $allowed
     */
    public static function assertAllowed(array $events, array $allowed, string $context): void
    {
        foreach ($events as $event) {
            if (!in_array($event, $allowed, true)) {
                throw new InvalidArgumentException(sprintf('Event "%s" is not allowed in the webhook object of %s', $event, $context));
            }
        }
    }

    private function __construct()
    {
    }
}
